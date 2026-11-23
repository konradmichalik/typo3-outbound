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

namespace KonradMichalik\Typo3Outbound\Codegen;

use function array_map;
use function implode;
use function is_numeric;
use function lcfirst;
use function preg_replace;
use function preg_split;
use function str_ends_with;
use function strtolower;
use function substr;
use function ucfirst;

/**
 * Names.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Names
{
    public static function studly(string $value): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $value) ?: [];
        $name = implode('', array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts));
        $name = (string) preg_replace('/^[^A-Za-z]+/', '', $name);

        return '' === $name ? 'Generated' : $name;
    }

    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    public static function property(string $key, bool $camel): string
    {
        $name = $camel ? self::camel($key) : (string) preg_replace('/\W/', '_', $key);

        return '' === $name || is_numeric($name[0]) ? 'field'.ucfirst($name) : $name;
    }

    public static function singular(string $value): string
    {
        return match (true) {
            str_ends_with($value, 'ies') => substr($value, 0, -3).'y',
            str_ends_with($value, 'ses') => substr($value, 0, -2),
            str_ends_with($value, 's') && !str_ends_with($value, 'ss') => substr($value, 0, -1),
            default => $value,
        };
    }
}
