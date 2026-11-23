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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Resilience;

use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Exception\{ApiUnauthorized, ApiUnavailable};
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Runtime\Dispatcher;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{ItemsApi, LimitedApi, LimitedOAuthApi};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';
require_once __DIR__.'/../Fixtures/RetryGuardDefinitions.php';

/**
 * RetryGuardsTest.
 *
 * A retry is a request like any other: it needs a rate limit token and a closed breaker.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Dispatcher::class)]
final class RetryGuardsTest extends TestCase
{
    #[Test]
    public function aRetryWithoutARateLimitTokenIsNotSentAndTheOriginalErrorRemains(): void
    {
        $mock = new MockTransport();
        $mock->on(LimitedApi::class, 'find')->respondWith(503)->respondWith(503)->respondWith(503)->respondWith(503);
        $api = Outbound::create([LimitedApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withExecutionContext(new SapiExecutionContext(true))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(LimitedApi::class);

        try {
            $api->find(1);
            self::fail('A 503 must not be turned into a result.');
        } catch (ApiUnavailable $exception) {
            self::assertSame(503, $exception->status);
        }

        self::assertSame(1, $mock->count(LimitedApi::class, 'find'));
    }

    #[Test]
    public function aServerQuotaThatResetsDuringTheBackoffAllowsTheRetry(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'find')
            ->respondWith(429, '', ['retry-after' => '1', 'x-ratelimit-remaining' => '0', 'x-ratelimit-reset' => '1'])
            ->respondWithJson(['id' => 1]);

        $this->api($mock, ItemsApi::class)->find(1);

        self::assertSame(2, $mock->count(ItemsApi::class, 'find'));
    }

    #[Test]
    public function noTokenIsRefreshedForA401ThatCannotBeResent(): void
    {
        $mock = new MockTransport();
        $mock->onKey('limited_o_auth::token')->respondWithJson(['access_token' => 'first', 'expires_in' => 3600])->respondWithJson(['access_token' => 'second', 'expires_in' => 3600]);
        $mock->on(LimitedOAuthApi::class, 'find')->respondWith(401)->respondWithJson('item');

        try {
            $this->api($mock, LimitedOAuthApi::class)->find(1);
            self::fail('Without a rate limit token the 401 stands.');
        } catch (ApiUnauthorized) {
        }

        self::assertCount(1, array_filter($mock->calls(), static fn ($call): bool => 'limited_o_auth::token' === $call->endpoint));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    private function api(MockTransport $mock, string $interface): object
    {
        return Outbound::create([$interface])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withExecutionContext(new SapiExecutionContext(true))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api($interface);
    }
}
