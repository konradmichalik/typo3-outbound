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

/**
 * Retry.
 *
 * Exponential backoff with jitter. Non-idempotent endpoints are never retried
 * unless `force` is set. A Retry-After header is honoured, but capped by the time
 * budget of the execution context.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Retry
{
    /**
     * @param list<int> $statusCodes
     */
    public function __construct(
        public int $maxRetries = 3,
        public array $statusCodes = [429, 502, 503, 504],
        public int $delayMs = 200,
        public float $multiplier = 2.0,
        public int $maxDelayMs = 2000,
        public bool $force = false,
    ) {}
}
