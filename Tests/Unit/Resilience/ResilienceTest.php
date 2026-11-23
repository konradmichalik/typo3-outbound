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

use KonradMichalik\Typo3Outbound\Attribute\{Connection, RateLimit, Retry};
use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Exception\ApiRateLimited;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Resilience\{InMemoryStateStore, RateLimitGuard, RetryPolicy};
use KonradMichalik\Typo3Outbound\Support\SystemClock;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, SwitchableCallContext};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\MyCRMApi;
use KonradMichalik\Typo3Outbound\Value\Setting;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * ResilienceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RetryPolicy::class)]
#[CoversClass(RateLimitGuard::class)]
final class ResilienceTest extends TestCase
{
    /**
     * symfony/rate-limiter before 7.4.22 reported a retry time that had already passed,
     * so a burst in the CLI gave up instead of waiting.
     */
    #[Test]
    public function aBurstInTheCliWaitsForEverySecondTokenInsteadOfFailing(): void
    {
        $connection = new ConnectionMetadata('burst', MyCRMApi::class, new Connection('https://x'), rateLimit: new RateLimit(2, '1 second'));
        $guard = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(false), new SystemClock());
        $start = microtime(true);

        for ($call = 0; $call < 4; ++$call) {
            $guard->acquire($connection, 'E');
        }

        self::assertGreaterThan(0.5, microtime(true) - $start);
    }

    #[Test]
    public function retryAfterBeyondTheCapGivesUpInsteadOfWaiting(): void
    {
        $policy = new RetryPolicy(new Retry(), true);

        self::assertSame(0.5, $policy->delay(1, '0.5', 1.0));
        self::assertNull($policy->delay(1, '60', 1.0));
        self::assertLessThanOrEqual(1.0, $policy->delay(1, null, 1.0));
    }

    #[Test]
    public function nonIdempotentRequestsAreOnlyRetriedWhenForced(): void
    {
        self::assertFalse((new RetryPolicy(new Retry(), false))->allows(1, 503));
        self::assertTrue((new RetryPolicy(new Retry(force: true), false))->allows(1, 503));
        self::assertFalse((new RetryPolicy(new Retry(), true))->allows(1, 400));
        self::assertFalse((new RetryPolicy(new Retry(maxRetries: 1), true))->allows(2, 503));
    }

    #[Test]
    public function exhaustedLimitFailsFastInWebAndWaitsInCli(): void
    {
        $connection = new ConnectionMetadata('crm', MyCRMApi::class, new Connection('https://x'), rateLimit: new RateLimit(1, '1 minute'));

        $clock = new FakeClock();
        $cli = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(false), $clock);
        $cli->acquire($connection, 'E');
        try {
            $cli->acquire($connection, 'E');
        } catch (ApiRateLimited) {
        }
        self::assertNotEmpty($clock->sleeps);

        $web = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(true), new FakeClock());
        $web->acquire($connection, 'E');
        $this->expectException(ApiRateLimited::class);
        $web->acquire($connection, 'E');
    }

    #[Test]
    public function announcedServerQuotaBlocksUntilReset(): void
    {
        $connection = new ConnectionMetadata('crm', MyCRMApi::class, new Connection('https://x'));
        $clock = new FakeClock();
        $guard = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(true), $clock);
        $guard->record($connection, ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => ['30']]);

        try {
            $guard->acquire($connection, 'E');
            self::fail('Expected the announced quota to block.');
        } catch (ApiRateLimited $exception) {
            self::assertSame(30, $exception->retryAfterSeconds);
        }
        $clock->advance(31);
        $guard->acquire($connection, 'E');
    }

    #[Test]
    public function aSiteSpecificConnectionKeepsItsQuotaAndLimitPerSite(): void
    {
        $connection = new ConnectionMetadata('crm', MyCRMApi::class, new Connection(new Setting('crm.baseUri'), allowedHosts: ['crm.example.com']), rateLimit: new RateLimit(1, '1 minute'));
        $context = new SwitchableCallContext('site_a');
        $guard = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(true), new FakeClock(), calls: $context);

        $guard->acquire($connection, 'E');
        $context->site = 'site_b';
        $guard->acquire($connection, 'E');

        $guard->record($connection, ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => ['30']]);
        $context->site = 'site_a';
        $this->expectException(ApiRateLimited::class);
        $guard->acquire($connection, 'E');
    }

    #[Test]
    public function aQuotaOfOneBaseUriIsSharedByAllSites(): void
    {
        $connection = new ConnectionMetadata('crm', MyCRMApi::class, new Connection('https://crm.example.com'));
        $context = new SwitchableCallContext('site_a');
        $guard = new RateLimitGuard(new InMemoryStateStore(), new SapiExecutionContext(true), new FakeClock(), calls: $context);
        $guard->record($connection, ['x-ratelimit-remaining' => ['0'], 'x-ratelimit-reset' => ['30']]);

        $context->site = 'site_b';
        $this->expectException(ApiRateLimited::class);
        $guard->acquire($connection, 'E');
    }
}
