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
 * CacheLock.
 *
 * Lets one process refresh an expired cache entry while others wait or serve
 * the stale entry. Locks are per cache key, so unrelated keys never wait for
 * each other.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface CacheLock
{
    /**
     * Waits at most `$timeout` seconds; null when the lock could not be acquired in time.
     */
    public function acquire(string $key, float $timeout): ?CacheLockHandle;
}
