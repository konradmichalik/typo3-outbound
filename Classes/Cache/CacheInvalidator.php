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
 * CacheInvalidator.
 *
 * Flushes tags after a successful write endpoint with #[InvalidatesCache]. In
 * TYPO3 this flushes all caches, so pages showing the data are cleared too.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface CacheInvalidator
{
    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags): void;
}
