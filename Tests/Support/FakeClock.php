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

namespace KonradMichalik\Typo3Outbound\Tests\Support;

use KonradMichalik\Outbound\Support\Clock;

/**
 * FakeClock.
 *
 * Time only moves when the code sleeps or the test advances it.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class FakeClock implements Clock
{
    /** @var list<float> */
    public array $sleeps = [];

    public function __construct(
        private float $now = 1_800_000_000.0,
    ) {}

    public function now(): float
    {
        return $this->now;
    }

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->now += $seconds;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }
}
