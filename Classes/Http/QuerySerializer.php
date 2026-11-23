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

use KonradMichalik\Typo3Outbound\Enum\QueryStyle;

use function array_is_list;
use function array_map;
use function implode;
use function is_array;
use function is_scalar;
use function ksort;
use function rawurlencode;
use function str_replace;

use const SORT_STRING;

/**
 * QuerySerializer.
 *
 * Writes normalized parameters into a query string. The result is sorted by
 * name, so equal filters in a different order produce the same URL and the same
 * cache key. Inside a DeepObject, lists are written comma-separated.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QuerySerializer
{
    /**
     * @param list<array{0: string, 1: mixed, 2: QueryStyle}> $parameters Name, normalized value, style
     */
    public static function build(array $parameters): string
    {
        $pairs = [];
        foreach ($parameters as [$name, $value, $style]) {
            foreach (self::pairs($name, $value, $style) as $pair) {
                $pairs[] = $pair;
            }
        }
        $sorted = [];
        foreach ($pairs as $index => [$name, $value]) {
            $sorted[$name."\0".$index] = self::encodeName($name).'='.rawurlencode($value);
        }
        ksort($sorted, SORT_STRING);

        return implode('&', $sorted);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function pairs(string $name, mixed $value, QueryStyle $style): array
    {
        if (null === $value || [] === $value) {
            return [];
        }
        if (!is_array($value)) {
            return [[$name, self::scalar($value)]];
        }
        if (array_is_list($value) && self::isFlat($value)) {
            return match ($style) {
                QueryStyle::CommaDelimited, QueryStyle::DeepObject => [[$name, implode(',', array_map(self::scalar(...), $value))]],
                QueryStyle::PipeDelimited => [[$name, implode('|', array_map(self::scalar(...), $value))]],
                QueryStyle::Brackets => array_map(static fn (mixed $item): array => [$name.'[]', self::scalar($item)], $value),
                QueryStyle::Form => array_map(static fn (mixed $item): array => [$name, self::scalar($item)], $value),
            };
        }

        $pairs = [];
        foreach ($value as $key => $member) {
            $nested = QueryStyle::Form === $style ? (string) $key : $name.'['.$key.']';
            $memberStyle = QueryStyle::Form === $style ? QueryStyle::Form : QueryStyle::DeepObject;
            foreach (self::pairs($nested, $member, $memberStyle) as $pair) {
                $pairs[] = $pair;
            }
        }

        return $pairs;
    }

    /**
     * @param list<mixed> $list
     */
    private static function isFlat(array $list): bool
    {
        foreach ($list as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private static function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function encodeName(string $name): string
    {
        return str_replace(['%5B', '%5D'], ['[', ']'], rawurlencode($name));
    }
}
