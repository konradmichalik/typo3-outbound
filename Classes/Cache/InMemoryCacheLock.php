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
 * InMemoryCacheLock.
 *
 * Process-local lock, used outside TYPO3 and in tests. Calls for the same key
 * within one process are coalesced before they reach the lock, so it never
 * needs to wait.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class InMemoryCacheLock implements CacheLock
{
    /** @var array<string, true> */
    private array $held = [];

    public function acquire(string $key, float $timeout): ?CacheLockHandle
    {
        if (isset($this->held[$key])) {
            return null;
        }
        $this->held[$key] = true;

        return new CacheLockHandle(function () use ($key): void {
            unset($this->held[$key]);
        });
    }
}
