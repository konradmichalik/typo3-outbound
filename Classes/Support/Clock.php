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

namespace KonradMichalik\Typo3Outbound\Support;

/**
 * Clock.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface Clock
{
    /**
     * Current time in seconds with microseconds.
     */
    public function now(): float;

    public function sleep(float $seconds): void;
}
