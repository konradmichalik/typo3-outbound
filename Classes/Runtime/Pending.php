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

namespace KonradMichalik\Typo3Outbound\Runtime;

use KonradMichalik\Typo3Outbound\Cache\{CacheEntry, CacheLockHandle};
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Pending.
 *
 * A started execution whose response has not been read yet. Carries the cache
 * entry read in CacheLayer::begin(), so finish() works on the same entry the
 * If-None-Match header was built from.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Pending
{
    public function __construct(
        public Execution $execution,
        public ?ResponseInterface $response,
        public float $startedAt,
        public ?Result $cached = null,
        public ?CacheEntry $entry = null,
        public ?Flight $flight = null,
        public bool $leader = false,
        public ?CacheLockHandle $lock = null,
        public int $reportLifetime = 0,
    ) {}
}
