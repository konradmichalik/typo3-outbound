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

namespace KonradMichalik\Typo3Outbound;

use CuyZ\Valinor\Cache\{Cache, FileSystemCache, FileWatchingCache};
use TYPO3\CMS\Core\Core\Environment;

/**
 * ValinorCacheFactory.
 *
 * Valinor's compiled type definitions under var/cache/code, next to TYPO3's
 * other code caches and flushed with them. Without it Valinor reflects every
 * DTO again in each request. In the Development context the cache watches the
 * DTO files, so an edited DTO takes effect without flushing.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ValinorCacheFactory
{
    /**
     * @return Cache<mixed>
     */
    public static function create(): Cache
    {
        $cache = new FileSystemCache(Environment::getVarPath().'/cache/code/outbound_valinor');

        return Environment::getContext()->isDevelopment() ? new FileWatchingCache($cache) : $cache;
    }
}
