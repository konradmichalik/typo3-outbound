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

use KonradMichalik\Typo3Outbound\Exception\BlockedRequest;

use function in_array;
use function is_string;
use function parse_url;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * HostGuard.
 *
 * Every URL a connection calls, including next-page links taken from responses,
 * must use https (unless the connection allows the private network) and point
 * to an allowed host. Without an explicit list, the host of the base URI is the
 * only allowed one.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class HostGuard
{
    /**
     * @param list<string> $allowedHosts
     */
    public static function check(string $url, string $baseUri, array $allowedHosts, bool $allowInsecure, string $endpoint): void
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        $host = parse_url($url, \PHP_URL_HOST);
        if (!is_string($host) || '' === $host || !is_string($scheme)) {
            throw new BlockedRequest(sprintf('%s: "%s" is not an absolute URL.', $endpoint, $url), 1794700060, $endpoint);
        }
        $scheme = strtolower($scheme);
        if ('https' !== $scheme && !('http' === $scheme && $allowInsecure)) {
            throw new BlockedRequest(sprintf('%s: only https URLs are allowed, got %s.', $endpoint, $scheme), 1794700061, $endpoint);
        }
        $allowed = [] !== $allowedHosts ? $allowedHosts : [(string) parse_url($baseUri, \PHP_URL_HOST)];
        if (!in_array(strtolower($host), array_map(strtolower(...), $allowed), true)) {
            throw new BlockedRequest(sprintf('%s: host "%s" is not allowed for this connection.', $endpoint, $host), 1794700062, $endpoint);
        }
    }

    /**
     * Resolves a possibly relative link against the URL it was found in.
     */
    public static function absolute(string $link, string $current): string
    {
        if (1 === preg_match('#^https?://#i', $link)) {
            return $link;
        }
        $scheme = (string) parse_url($current, \PHP_URL_SCHEME);
        $host = (string) parse_url($current, \PHP_URL_HOST);
        $port = parse_url($current, \PHP_URL_PORT);
        $origin = $scheme.'://'.$host.(null !== $port && false !== $port ? ':'.$port : '');
        if (str_starts_with($link, '/')) {
            return $origin.$link;
        }
        $path = (string) parse_url($current, \PHP_URL_PATH);
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin.('' === $directory ? '/' : $directory).$link;
    }
}
