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
 * CacheLockHandle.
 *
 * Released explicitly or, for a Deferred that is never resolved, when the
 * handle is garbage-collected, at the latest at the end of the process.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheLockHandle
{
    private bool $released = false;

    /**
     * @param \Closure(): void $release
     * @param bool            $waited  True when another process held the lock first
     */
    public function __construct(
        private readonly \Closure $release,
        public readonly bool $waited = false,
    ) {}

    public function __destruct()
    {
        $this->release();
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        ($this->release)();
    }
}
