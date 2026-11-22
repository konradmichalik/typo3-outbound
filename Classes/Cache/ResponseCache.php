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

/**
 * ResponseCache.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ResponseCache
{
    public function get(string $key): ?CacheEntry;

    /**
     * @param list<string> $tags
     */
    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void;

    /**
     * @param list<string> $tags
     */
    public function flushTags(array $tags): void;
}
