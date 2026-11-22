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
use KonradMichalik\Typo3Outbound\Exception\{ApiException, ApiRateLimited};
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Runtime\{Dispatcher, Settings};
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\ItemsApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';

/**
 * RetryBehaviourTest.
 *
 * Retries as the dispatcher runs them: waiting time, caps and the end of the road.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Dispatcher::class)]
final class RetryBehaviourTest extends TestCase
{
    private MockTransport $mock;
    private FakeClock $clock;
    private string $timezone;

    protected function setUp(): void
    {
        $this->mock = new MockTransport();
        $this->clock = new FakeClock();
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    #[Test]
    public function aRetryAfterInSecondsIsWaitedForExactly(): void
    {
        $this->mock->on(ItemsApi::class, 'find')->respondWith(503, '', ['retry-after' => '2'])->respondWithJson(['id' => 1]);

        $item = $this->api(web: false)->find(1);

        self::assertSame(1, $item->id);
        self::assertSame([2.0], $this->clock->sleeps);
        self::assertSame(2, $this->mock->count(ItemsApi::class, 'find'));
    }

    #[Test]
    public function aRetryAfterDateIsReadAsGmtWhateverTheDefaultTimezoneIs(): void
    {
        date_default_timezone_set('Europe/Berlin');
        $date = gmdate('D, d M Y H:i:s \G\M\T', (int) $this->clock->now() + 3);
        $this->mock->on(ItemsApi::class, 'find')->respondWith(503, '', ['retry-after' => $date])->respondWithJson(['id' => 1]);

        $this->api(web: false)->find(1);

        self::assertSame([3.0], $this->clock->sleeps);
    }

    #[Test]
    public function inAWebRequestALongRetryAfterIsNotWaitedFor(): void
    {
        $this->mock->on(ItemsApi::class, 'find')->respondWith(503, '', ['retry-after' => '5']);

        try {
            $this->api(web: true)->find(1);
            self::fail('Expected the failure to surface instead of waiting.');
        } catch (ApiException) {
        }

        self::assertSame(1, $this->mock->count(ItemsApi::class, 'find'));
        self::assertSame([], $this->clock->sleeps);
    }

    #[Test]
    public function aWaitThatWouldExhaustTheRequestBudgetIsSkipped(): void
    {
        $this->mock->on(ItemsApi::class, 'find')->respondWith(503, '', ['retry-after' => '5']);

        try {
            $this->api(web: true, settings: new Settings(webBudget: 2.0, webRetryAfterCap: 10.0))->find(1);
            self::fail('Expected the failure to surface instead of waiting.');
        } catch (ApiException) {
        }

        self::assertSame(1, $this->mock->count(ItemsApi::class, 'find'));
    }

    #[Test]
    public function aBackoffThatWouldExhaustTheRequestBudgetIsSkipped(): void
    {
        $this->mock->on(ItemsApi::class, 'find')->respondWith(503);

        try {
            $this->api(web: true, settings: new Settings(webBudget: 0.01))->find(1);
            self::fail('Expected the failure to surface instead of waiting.');
        } catch (ApiException) {
        }

        self::assertSame(1, $this->mock->count(ItemsApi::class, 'find'));
        self::assertSame([], $this->clock->sleeps);
    }

    #[Test]
    public function aRateLimitThatPersistsEndsAsRateLimited(): void
    {
        $this->mock->on(ItemsApi::class, 'find')->respondWith(429, '', ['retry-after' => '1']);

        try {
            $this->api(web: false)->find(1);
            self::fail('Expected ApiRateLimited.');
        } catch (ApiRateLimited $exception) {
            self::assertSame(1, $exception->retryAfterSeconds);
        }

        self::assertSame(2, $this->mock->count(ItemsApi::class, 'find'));
    }

    private function api(bool $web, ?Settings $settings = null): ItemsApi
    {
        $outbound = Outbound::create([ItemsApi::class])
            ->withTransport($this->mock)
            ->withClock($this->clock)
            ->withExecutionContext(new SapiExecutionContext($web))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'));

        return (null !== $settings ? $outbound->withSettings($settings) : $outbound)->api(ItemsApi::class);
    }
}
