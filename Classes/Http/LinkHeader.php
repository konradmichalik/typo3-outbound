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

namespace KonradMichalik\Typo3Outbound\Http;

use function explode;
use function in_array;
use function preg_match;
use function strtolower;
use function trim;

/**
 * LinkHeader.
 *
 * Minimal RFC 8288 parser for the `rel="next"` relation.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LinkHeader
{
    public static function next(?string $header): ?string
    {
        if (null === $header || '' === trim($header)) {
            return null;
        }
        // Links are separated by commas before "<"; a comma inside the URL belongs to it.
        foreach (preg_split('/,(?=\s*<)/', $header) ?: [] as $link) {
            if (1 !== preg_match('/<([^>]+)>(.*)$/', trim($link), $match)) {
                continue;
            }
            if (1 === preg_match('/;\s*rel\s*=\s*"?([^";]+)"?/i', $match[2], $rel) && in_array('next', explode(' ', strtolower(trim($rel[1]))), true)) {
                return $match[1];
            }
        }

        return null;
    }
}
