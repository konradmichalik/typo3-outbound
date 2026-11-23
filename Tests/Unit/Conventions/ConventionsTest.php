<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_outbound" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Conventions;

use KonradMichalik\Typo3Outbound\Exception\ApiConflict;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Resilience\InMemoryStateStore;
use KonradMichalik\Typo3Outbound\Runtime\ConventionObserver;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, RecordingLogger};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{DocumentDto, DocumentsApi};
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use KonradMichalik\Typo3Outbound\Value\FilePart;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

require_once __DIR__.'/../Fixtures/ConventionDefinitions.php';

/**
 * ConventionsTest.
 *
 * OAuth2 client credentials, ETags, multipart uploads and deprecation headers.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class ConventionsTest extends TestCase
{
    private MockTransport $mock;
    private FakeClock $clock;
    private InMemoryStateStore $store;
    private RecordingLogger $logger;

    protected function setUp(): void
    {
        putenv('OUTBOUND_TEST_CLIENT_ID=client');
        putenv('OUTBOUND_TEST_CLIENT_SECRET=very-secret');
        $this->mock = new MockTransport();
        $this->clock = new FakeClock();
        $this->store = new InMemoryStateStore();
        $this->logger = new RecordingLogger();
    }

    #[Test]
    public function oauthTokenIsFetchedOnceAndSentAsBearer(): void
    {
        $this->mock->onKey('documents::token')->respondWithJson(['access_token' => 'token-1', 'expires_in' => 3600]);
        $this->mock->on(DocumentsApi::class, 'find')->respondWithJson(['id' => 1, 'title' => 'A']);
        $api = $this->api();

        $api->find(1);
        $this->clock->advance(120);
        $api->find(2);

        $token = $this->mock->calls()[0];
        self::assertSame('documents::token', $token->endpoint);
        self::assertSame('grant_type=client_credentials&client_id=client&client_secret=very-secret&scope=documents', $token->request->body);
        self::assertCount(1, array_filter($this->mock->calls(), static fn (Call $call): bool => 'documents::token' === $call->endpoint));
        self::assertSame('Bearer token-1', $this->mock->calls(DocumentsApi::class, 'find')[1]->request->headers['Authorization']);
        self::assertGreaterThan(0.0, $token->options->maxDuration, 'the token request is bounded like every other call');
    }

    #[Test]
    public function rejectedTokenIsRefreshedOnceAndTheCallRepeated(): void
    {
        $this->mock->onKey('documents::token')->respondWithJson(['access_token' => 'old', 'expires_in' => 3600])->respondWithJson(['access_token' => 'new', 'expires_in' => 3600]);
        $this->mock->on(DocumentsApi::class, 'find')->respondWith(401)->respondWithJson(['id' => 1]);

        self::assertSame(1, $this->api()->find(1)->id);
        $finds = $this->mock->calls(DocumentsApi::class, 'find');
        self::assertSame('Bearer old', $finds[0]->request->headers['Authorization']);
        self::assertSame('Bearer new', $finds[1]->request->headers['Authorization']);
    }

    #[Test]
    public function expiredEntryIsRevalidatedWithItsEtag(): void
    {
        $this->mock->onKey('documents::token')->respondWithJson(['access_token' => 't', 'expires_in' => 3600]);
        $this->mock->on(DocumentsApi::class, 'find')->respondWithJson(['id' => 1, 'title' => 'cached'], 200, ['etag' => '"v1"'])->respondWith(304);
        $api = $this->api();
        $api->find(1);

        $this->clock->advance(61);
        $document = $api->find(1);

        self::assertSame('cached', $document->title);
        self::assertSame('"v1"', $this->mock->calls(DocumentsApi::class, 'find')[1]->request->headers['If-None-Match']);
    }

    #[Test]
    public function updateSendsTheLastEtagAsIfMatchAndMapsA412(): void
    {
        $this->mock->onKey('documents::token')->respondWithJson(['access_token' => 't', 'expires_in' => 3600]);
        $this->mock->on(DocumentsApi::class, 'find')->respondWithJson(['id' => 1], 200, ['etag' => '"v7"']);
        $this->mock->on(DocumentsApi::class, 'update')->respondWith(412);
        $api = $this->api();
        $api->find(1);

        try {
            $api->update(1, new DocumentDto(1, 'changed'));
            self::fail('Expected a conflict.');
        } catch (ApiConflict $exception) {
            self::assertSame(412, $exception->status);
        }
        self::assertSame('"v7"', $this->mock->calls(DocumentsApi::class, 'update')[0]->request->headers['If-Match']);
    }

    #[Test]
    public function multipartBodyCarriesFieldsAndFiles(): void
    {
        $transport = new class implements Transport {
            public string $body = '';
            public string $contentType = '';

            public function request(Call $call): ResponseInterface
            {
                if (str_ends_with($call->endpoint, '::token')) {
                    return (new MockHttpClient(new MockResponse('{"access_token":"t","expires_in":3600}')))->request('POST', $call->request->url);
                }
                foreach (is_iterable($call->request->body) ? $call->request->body : [(string) $call->request->body] as $chunk) {
                    $this->body .= $chunk;
                }
                $this->contentType = $call->request->headers['Content-Type'] ?? '';

                return (new MockHttpClient(new MockResponse('{"id":5}', ['response_headers' => ['content-type' => 'application/json']])))->request('POST', $call->request->url);
            }
        };

        $document = $this->api($transport)->upload(['title' => 'Report', 'file' => FilePart::fromContents('%PDF-1.7', 'report.pdf', 'application/pdf')]);

        self::assertSame(5, $document->id);
        self::assertStringStartsWith('multipart/form-data; boundary=', $transport->contentType);
        self::assertStringContainsString('name="title"', $transport->body);
        self::assertStringContainsString('filename="report.pdf"', $transport->body);
        self::assertStringContainsString('%PDF-1.7', $transport->body);
    }

    #[Test]
    public function deprecationHeadersAreLoggedOnceAndKept(): void
    {
        $this->mock->onKey('documents::token')->respondWithJson(['access_token' => 't', 'expires_in' => 3600]);
        $this->mock->on(DocumentsApi::class, 'update')->respondWithJson(['id' => 1], 200, ['sunset' => 'Wed, 01 Jul 2026 00:00:00 GMT', 'deprecation' => '@1767225600']);
        $api = $this->api();

        $api->update(1, new DocumentDto(1));
        $api->update(1, new DocumentDto(1));

        $warnings = array_filter($this->logger->records, static fn (array $record): bool => 'warning' === $record['level'] && str_contains($record['message'], 'deprecated'));
        self::assertCount(1, $warnings);
        self::assertSame('Wed, 01 Jul 2026 00:00:00 GMT', $this->store->get(ConventionObserver::deprecationKey('DocumentsApi::update'))['sunset'] ?? null);
    }

    private function api(?Transport $transport = null): DocumentsApi
    {
        return Outbound::create([DocumentsApi::class])
            ->withTransport($transport ?? $this->mock)
            ->withClock($this->clock)
            ->withStateStore($this->store)
            ->withLogger($this->logger)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(DocumentsApi::class);
    }
}
