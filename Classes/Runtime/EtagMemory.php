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

namespace KonradMichalik\Typo3Outbound\Runtime;

use function parse_url;
use function sha1;

/**
 * EtagMemory.
 *
 * The last ETag seen for a resource path, so an update declared with
 * `ifMatch: true` can send it as If-Match.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class EtagMemory
{
    public const TTL = 86_400;

    public static function key(string $connection, string $url): string
    {
        return 'etag_'.sha1($connection.' '.(string) parse_url($url, PHP_URL_HOST).(string) parse_url($url, PHP_URL_PATH));
    }
}
