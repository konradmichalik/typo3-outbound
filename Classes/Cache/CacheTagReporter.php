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
 * CacheTagReporter.
 *
 * Passes the tags and lifetime of API data to the cache of whatever renders it,
 * in TYPO3 the page cache.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface CacheTagReporter
{
    /**
     * @param list<string> $tags
     */
    public function report(array $tags, int $lifetime): void;
}
