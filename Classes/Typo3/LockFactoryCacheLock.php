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

namespace KonradMichalik\Typo3Outbound\Typo3;

use KonradMichalik\Typo3Outbound\Cache\{CacheLock, CacheLockHandle};
use KonradMichalik\Typo3Outbound\Support\Clock;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\{LockFactory, LockingStrategyInterface};

use function sha1;

/**
 * LockFactoryCacheLock.
 *
 * Uses TYPO3's locking API, which picks the strategy. File and semaphore locks
 * only coordinate processes on one server.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class LockFactoryCacheLock implements CacheLock
{
    private const POLL_INTERVAL = 0.05;

    public function __construct(
        private LockFactory $locks,
        private Clock $clock,
    ) {}

    public function acquire(string $key, float $timeout): ?CacheLockHandle
    {
        $mode = LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK;
        $locker = $this->locks->createLocker('outbound_'.sha1($key), $mode);
        $deadline = $this->clock->now() + $timeout;
        $waited = false;
        while (true) {
            try {
                if ($locker->acquire($mode)) {
                    return new CacheLockHandle(static function () use ($locker): void {
                        $locker->release();
                        // The file lock keeps its file after release. One per distinct request would pile up in var/lock.
                        $locker->destroy();
                    }, $waited);
                }
            } catch (LockAcquireWouldBlockException) {
            }
            if ($this->clock->now() >= $deadline) {
                return null;
            }
            $waited = true;
            $this->clock->sleep(self::POLL_INTERVAL);
        }
    }
}
