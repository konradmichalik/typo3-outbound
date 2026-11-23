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

use KonradMichalik\Typo3Outbound\Auth\TokenProvider;
use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Exception\ApiTransportFailed;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\TokenBudgetApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/BudgetDefinitions.php';

/**
 * TokenBudgetTest.
 *
 * Symfony treats a max_duration of 0 as unlimited, so an exhausted web budget must stop
 * the token request instead of sending it without any time limit.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(TokenProvider::class)]
final class TokenBudgetTest extends TestCase
{
    #[Test]
    public function noTokenIsRequestedOnceTheWebBudgetIsExhausted(): void
    {
        $clock = new FakeClock();
        $mock = new MockTransport();
        $mock->onKey('token_budget::token')->respondWithJson(['access_token' => 'first', 'expires_in' => 31])->respondWithJson(['access_token' => 'second', 'expires_in' => 31]);
        $mock->on(TokenBudgetApi::class, 'find')->respondWithJson('one')->respondWithJson('two');
        $api = Outbound::create([TokenBudgetApi::class])
            ->withTransport($mock)
            ->withClock($clock)
            ->withExecutionContext(new SapiExecutionContext(true))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(TokenBudgetApi::class);
        $api->find(1);
        $clock->advance(9.0);

        try {
            $api->find(2);
            self::fail('The exhausted budget must stop the call.');
        } catch (ApiTransportFailed) {
        }

        self::assertCount(1, array_filter($mock->calls(), static fn ($call): bool => 'token_budget::token' === $call->endpoint));
    }

    #[Test]
    public function aLifetimeSentAsStringIsHonoured(): void
    {
        $clock = new FakeClock();
        $mock = new MockTransport();
        $mock->onKey('token_budget::token')->respondWithJson(['access_token' => 'first', 'expires_in' => '3600'])->respondWithJson(['access_token' => 'second', 'expires_in' => '3600']);
        $mock->on(TokenBudgetApi::class, 'find')->respondWithJson('one')->respondWithJson('two');
        $api = Outbound::create([TokenBudgetApi::class])
            ->withTransport($mock)
            ->withClock($clock)
            ->withExecutionContext(new SapiExecutionContext(false))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(TokenBudgetApi::class);
        $api->find(1);
        $clock->advance(600.0);
        $api->find(2);

        self::assertCount(1, array_filter($mock->calls(), static fn ($call): bool => 'token_budget::token' === $call->endpoint));
    }
}
