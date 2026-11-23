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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Runtime;

use KonradMichalik\Typo3Outbound\Context\ValueResolver;
use KonradMichalik\Typo3Outbound\Exception\BlockedRequest;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Runtime\Dispatcher;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\StaticHeaderApi;
use KonradMichalik\Typo3Outbound\Transport\SymfonyTransport;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

require_once __DIR__.'/../Fixtures/SecurityDefinitions.php';

/**
 * HeaderValueTest.
 *
 * Symfony rejects header values with CR/LF and quotes the whole header line in its
 * exception, credentials included. Such a value must neither reach the caller nor a log.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Dispatcher::class)]
#[CoversClass(SymfonyTransport::class)]
#[CoversClass(ValueResolver::class)]
final class HeaderValueTest extends TestCase
{
    /** @var list<mixed> */
    private array $sentHeaders = [];

    protected function tearDown(): void
    {
        putenv('OUTBOUND_TEST_KEY');
    }

    #[Test]
    public function aTrailingNewlineOfAnEnvironmentValueIsDropped(): void
    {
        putenv("OUTBOUND_TEST_KEY=top-secret-key\n");

        self::assertSame('ok', $this->api()->a());
        self::assertIsArray($this->sentHeaders[0] ?? null);
        self::assertContains('X-Static: top-secret-key', $this->sentHeaders[0]);
    }

    #[Test]
    public function aLineBreakInsideAHeaderValueIsBlockedWithoutQuotingIt(): void
    {
        putenv("OUTBOUND_TEST_KEY=top-secret\r\nkey");

        try {
            $this->api()->a();
            self::fail('A header value with a line break must not be sent.');
        } catch (BlockedRequest $exception) {
            for ($e = $exception; null !== $e; $e = $e->getPrevious()) {
                self::assertStringNotContainsString('top-secret', $e->getMessage());
            }
        }

        self::assertSame([], $this->sentHeaders);
    }

    private function api(): StaticHeaderApi
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->sentHeaders[] = $options['headers'] ?? null;

            return new MockResponse('ok', ['response_headers' => ['content-type: text/plain']]);
        });

        return Outbound::create([StaticHeaderApi::class])
            ->withClock(new FakeClock())
            ->withTransport(new SymfonyTransport($client))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(StaticHeaderApi::class);
    }
}
