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

use function hash;
use function implode;

/**
 * CacheKey.
 *
 * Different sites, languages, principals, request bodies and header
 * parameters never share an entry. `variant` carries the last two.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheKey
{
    public static function for(string $endpoint, string $url, string $identity, ?string $language, string $variant = ''): string
    {
        $parts = [$endpoint, $url, $identity, $language ?? ''];
        if ('' !== $variant) {
            $parts[] = $variant;
        }

        return 'outbound_'.hash('sha256', implode("\n", $parts));
    }
}
