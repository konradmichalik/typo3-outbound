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

use KonradMichalik\Typo3Outbound\Resilience\RetryPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * RetryAfterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RetryPolicy::class)]
final class RetryAfterTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    #[Test]
    public function aHttpDateIsReadAsGmtWhateverTheDefaultTimezoneIs(): void
    {
        date_default_timezone_set('Europe/Berlin');
        $now = (float) gmmktime(7, 27, 0, 10, 21, 2026);

        self::assertSame(60.0, RetryPolicy::parseRetryAfter('Wed, 21 Oct 2026 07:28:00 GMT', $now));
    }

    #[Test]
    public function secondsAreTakenAsTheyAre(): void
    {
        self::assertSame(120.0, RetryPolicy::parseRetryAfter('120', 0.0));
    }

    #[Test]
    public function garbageIsIgnored(): void
    {
        self::assertNull(RetryPolicy::parseRetryAfter('soon', 0.0));
    }
}
