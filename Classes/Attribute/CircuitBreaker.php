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

namespace KonradMichalik\Typo3Outbound\Attribute;

use Attribute;

/**
 * CircuitBreaker.
 *
 * Opens after `failureThreshold` consecutive failures of the connection; calls
 * then fail immediately until `cooldown` seconds have passed.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class CircuitBreaker
{
    public function __construct(
        public int $failureThreshold = 5,
        public int $cooldown = 30,
    ) {}
}
