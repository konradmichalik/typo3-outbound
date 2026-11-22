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

namespace KonradMichalik\Typo3Outbound\Support;

use function array_map;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_contains;
use function strtolower;
use function urldecode;

/**
 * Redactor.
 *
 * Removes credentials from URLs and headers before they reach a log, an
 * exception or debug output.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Redactor
{
    public const MASK = '[redacted]';

    /**
     * Matched anywhere in a name, so vendor variants (`x-auth-token`, `refresh_token`) are covered
     * as well. Masking a harmless value too often costs nothing, leaking a credential does.
     */
    private const SENSITIVE_NAME = '/token|secret|passw|api[-_]?key|auth(?!ors?(?![a-z]))|jwt|signature|credential|cookie|session|^(key|sig)$/i';

    /**
     * @param list<string> $extraKeys Additional sensitive query keys, e.g. from #[Sensitive] or an API key name
     */
    public static function url(string $url, array $extraKeys = []): string
    {
        $url = (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@?\#]*@#i', '$1'.self::MASK.'@', $url);
        if (!str_contains($url, '?')) {
            return $url;
        }
        [$base, $query] = explode('?', $url, 2);
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $pairs[] = self::isSensitive(urldecode($name), $extraKeys) ? $name.'='.self::MASK : $name.'='.$value;
        }

        return $base.'?'.implode('&', $pairs);
    }

    /**
     * @param array<string, string> $headers
     * @param list<string>          $extraHeaders
     *
     * @return array<string, string>
     */
    public static function headers(array $headers, array $extraHeaders = []): array
    {
        $redacted = [];
        foreach ($headers as $name => $value) {
            $redacted[$name] = self::isSensitive($name, $extraHeaders) ? self::MASK : $value;
        }

        return $redacted;
    }

    /**
     * @param list<string> $extraKeys
     */
    private static function isSensitive(string $name, array $extraKeys): bool
    {
        // `token[]` is the parameter `token`, `filter[token]` carries one: look at the whole name
        // and at the part in front of the first bracket.
        $base = (string) preg_replace('/\[.*$/s', '', $name);

        return 1 === preg_match(self::SENSITIVE_NAME, $name)
            || 1 === preg_match(self::SENSITIVE_NAME, $base)
            || in_array(strtolower($base), array_map('strtolower', $extraKeys), true);
    }
}
