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

namespace KonradMichalik\Typo3Outbound\Cache;

use KonradMichalik\Outbound\Cache\CacheInvalidator;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * CacheManagerInvalidator.
 *
 * Flushes tags in all caches: cached responses and pages that rendered them.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CacheManagerInvalidator implements CacheInvalidator
{
    public function __construct(
        private CacheManager $caches,
    ) {}

    public function invalidate(array $tags): void
    {
        if ([] !== $tags) {
            $this->caches->flushCachesByTags($tags);
        }
    }
}
