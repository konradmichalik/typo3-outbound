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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Auth;

use KonradMichalik\Typo3Outbound\Auth\{Authenticator, TokenProvider};
use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Exception\{ApiUnauthorized, BlockedRequest};
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, RecordingLogger};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{AbsoluteTokenLoginApi, ConstantFirstFieldLoginApi, EnvBaseLoginApi, TokenLoginApi};
use KonradMichalik\Typo3Outbound\Transport\Call;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_values;
use function json_encode;

require_once __DIR__.'/../Fixtures/TokenLoginDefinitions.php';

/**
 * TokenLoginTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(TokenProvider::class)]
#[CoversClass(Authenticator::class)]
final class TokenLoginTest extends TestCase
{
    private const PASSWORD = 'pa55-word-value';

    private const LOGIN = 'token_login::token';

    private FakeClock $clock;

    private MockTransport $mock;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        putenv('OUTBOUND_TEST_PASSWORD='.self::PASSWORD);
        $this->clock = new FakeClock();
        $this->mock = new MockTransport();
        $this->logger = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        putenv('OUTBOUND_TEST_PASSWORD');
        putenv('OUTBOUND_TEST_LOGIN_BASE');
        putenv('OUTBOUND_TEST_LOGIN_USER');
    }

    #[Test]
    public function aChangedAccountLogsInAgainEvenBehindAConstantField(): void
    {
        $this->mock->onKey('constant_first_field_login::token')->respondWithJson(['token' => 'alice-token'])->respondWithJson(['token' => 'bob-token']);
        $this->mock->on(ConstantFirstFieldLoginApi::class, 'find')->respondWithJson('one')->respondWithJson('two');
        $api = $this->api(ConstantFirstFieldLoginApi::class);

        putenv('OUTBOUND_TEST_LOGIN_USER=alice');
        $api->find(1);
        putenv('OUTBOUND_TEST_LOGIN_USER=bob');
        $api->find(2);

        self::assertCount(2, $this->calls('constant_first_field_login::token'));
        self::assertSame('Bearer bob-token', $this->mock->calls(ConstantFirstFieldLoginApi::class, 'find')[1]->request->headers['Authorization'] ?? null);
    }

    #[Test]
    public function aLoginBodyThatCannotBeEncodedThrowsUnauthorized(): void
    {
        putenv("OUTBOUND_TEST_PASSWORD=\xB1");

        $this->expectException(ApiUnauthorized::class);
        $this->expectExceptionCode(1794700242);

        $this->api(TokenLoginApi::class)->find(1);
    }

    #[Test]
    public function logsInOnceAndSendsTheTokenAsBearer(): void
    {
        $this->mock->onKey(self::LOGIN)->respondWithJson(['token' => 'abc']);
        $this->mock->on(TokenLoginApi::class, 'find')->respondWithJson('one')->respondWithJson('two');
        $api = $this->api(TokenLoginApi::class);

        $api->find(1);
        $api->find(2);

        $logins = $this->calls(self::LOGIN);
        self::assertCount(1, $logins);
        self::assertSame('POST', $logins[0]->request->method);
        self::assertSame('https://login.example.com/auth/token', $logins[0]->request->url);
        self::assertSame('application/json', $logins[0]->request->headers['Content-Type'] ?? null);
        self::assertSame(json_encode(['username' => 'user', 'password' => self::PASSWORD]), $logins[0]->request->body);
        $finds = $this->mock->calls(TokenLoginApi::class, 'find');
        self::assertSame('Bearer abc', $finds[1]->request->headers['Authorization'] ?? null);
    }

    #[Test]
    public function anAbsoluteLoginUrlIsUsedAsGiven(): void
    {
        $this->mock->onKey('absolute_token_login::token')->respondWithJson(['token' => 'abc']);
        $this->mock->on(AbsoluteTokenLoginApi::class, 'find')->respondWithJson('one');

        $this->api(AbsoluteTokenLoginApi::class)->find(1);

        self::assertSame('https://auth.example.com/login', $this->calls('absolute_token_login::token')[0]->request->url);
    }

    #[Test]
    public function credentialsAreNeverSentToAHostOutsideTheAllowList(): void
    {
        putenv('OUTBOUND_TEST_LOGIN_BASE=https://login.example.org');
        $this->mock->onKey('env_base_login::token')->respondWithJson(['token' => 'abc']);

        try {
            $this->api(EnvBaseLoginApi::class)->find(1);
            self::fail('A login host outside allowedHosts must be blocked.');
        } catch (BlockedRequest) {
        }
        self::assertCount(0, $this->calls('env_base_login::token'));
    }

    #[Test]
    public function aChangedBaseUriLogsInAgainInsteadOfReusingTheToken(): void
    {
        $this->mock->onKey('env_base_login::token')->respondWithJson(['token' => 'live'])->respondWithJson(['token' => 'sandbox']);
        $this->mock->on(EnvBaseLoginApi::class, 'find')->respondWithJson('one')->respondWithJson('two');
        $api = $this->api(EnvBaseLoginApi::class);

        putenv('OUTBOUND_TEST_LOGIN_BASE=https://login.example.com');
        $api->find(1);
        putenv('OUTBOUND_TEST_LOGIN_BASE=https://sandbox.login.example.com');
        $api->find(2);

        self::assertCount(2, $this->calls('env_base_login::token'));
        self::assertSame('Bearer sandbox', $this->mock->calls(EnvBaseLoginApi::class, 'find')[1]->request->headers['Authorization'] ?? null);
    }

    #[Test]
    public function theTokenIsKeptUntilTheTtlMinusTheMargin(): void
    {
        $this->mock->onKey(self::LOGIN)->respondWithJson(['token' => 'first'])->respondWithJson(['token' => 'second']);
        $this->mock->on(TokenLoginApi::class, 'find')->respondWithJson('one')->respondWithJson('two')->respondWithJson('three');
        $api = $this->api(TokenLoginApi::class);

        $api->find(1);
        $this->clock->advance(569.0);
        $api->find(2);
        self::assertCount(1, $this->calls(self::LOGIN));

        $this->clock->advance(2.0);
        $api->find(3);
        self::assertCount(2, $this->calls(self::LOGIN));
    }

    #[Test]
    public function aRejectedTokenLeadsToExactlyOneNewLogin(): void
    {
        $this->mock->onKey(self::LOGIN)->respondWithJson(['token' => 'old'])->respondWithJson(['token' => 'new']);
        $this->mock->on(TokenLoginApi::class, 'find')->respondWith(401)->respondWithJson('fresh');

        self::assertSame('fresh', $this->api(TokenLoginApi::class)->find(1));
        self::assertCount(2, $this->calls(self::LOGIN));
        self::assertSame('Bearer new', $this->mock->calls(TokenLoginApi::class, 'find')[1]->request->headers['Authorization'] ?? null);
    }

    #[Test]
    public function aFailedLoginThrowsUnauthorizedWithoutLoop(): void
    {
        $this->mock->onKey(self::LOGIN)->respondWith(401)->respondWith(401);
        $this->mock->on(TokenLoginApi::class, 'find')->respondWithJson('never');

        try {
            $this->api(TokenLoginApi::class)->find(1);
            self::fail('A rejected login must throw.');
        } catch (ApiUnauthorized $exception) {
            self::assertSame(1794700243, $exception->getCode());
        }
        self::assertCount(1, $this->calls(self::LOGIN));
        self::assertCount(0, $this->mock->calls(TokenLoginApi::class, 'find'));
    }

    #[Test]
    #[DataProvider('unusableLoginResponses')]
    public function anUnusableLoginResponseThrowsWithoutEchoingTheBody(string $body): void
    {
        $this->mock->onKey(self::LOGIN)->respondWith(200, $body, ['Content-Type' => 'application/json']);

        try {
            $this->api(TokenLoginApi::class)->find(1);
            self::fail('An unusable login response must throw.');
        } catch (ApiUnauthorized $exception) {
            self::assertSame(1794700243, $exception->getCode());
            self::assertStringNotContainsString('secret-marker', $exception->getMessage());
        }
        self::assertStringNotContainsString('secret-marker', (string) json_encode($this->logger->records));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableLoginResponses(): iterable
    {
        yield 'html' => ['<html>secret-marker</html>'];
        yield 'token null' => ['{"token":null,"hint":"secret-marker"}'];
        yield 'token number' => ['{"token":42,"hint":"secret-marker"}'];
        yield 'token missing' => ['{"other":"secret-marker"}'];
    }

    #[Test]
    public function aTransportFailureOfTheLoginThrowsUnauthorized(): void
    {
        $this->mock->onKey(self::LOGIN)->failWith();

        $this->expectException(ApiUnauthorized::class);
        $this->expectExceptionCode(1794700242);

        $this->api(TokenLoginApi::class)->find(1);
    }

    #[Test]
    public function credentialsAndTheTokenNeverReachTheLog(): void
    {
        $this->mock->onKey(self::LOGIN)->respondWithJson(['token' => 'tok-en-value']);
        $this->mock->on(TokenLoginApi::class, 'find')->respondWithJson('one');

        $this->api(TokenLoginApi::class)->find(1);

        $log = (string) json_encode($this->logger->records);
        self::assertStringNotContainsString(self::PASSWORD, $log);
        self::assertStringNotContainsString('tok-en-value', $log);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    private function api(string $interface): object
    {
        return Outbound::create([$interface])
            ->withTransport($this->mock)
            ->withClock($this->clock)
            ->withLogger($this->logger)
            ->withExecutionContext(new SapiExecutionContext(false))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api($interface);
    }

    /**
     * @return list<Call>
     */
    private function calls(string $key): array
    {
        return array_values(array_filter($this->mock->calls(), static fn (Call $call): bool => $key === $call->endpoint));
    }
}
