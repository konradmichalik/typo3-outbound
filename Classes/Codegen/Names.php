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
use function in_array;
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
    /**
     * Class names PHP reserves, as keywords or built-in types; generated classes get a suffix instead.
     */
    private const RESERVED = ['abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void', 'while', 'xor', 'yield'];

    public static function studly(string $value): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $value) ?: [];
        $name = implode('', array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts));
        $name = (string) preg_replace('/^[^A-Za-z]+/', '', $name);

        return '' === $name ? 'Generated' : $name;
    }

    public static function className(string $value): string
    {
        $name = self::studly($value);

        return in_array(strtolower($name), self::RESERVED, true) ? $name.'Dto' : $name;
    }

    /**
     * Case names for enum values, in the order of the values: values starting with something other
     * than a letter get a `Value` prefix, and values that normalize to the same name are numbered.
     *
     * @param list<string> $values
     *
     * @return list<string>
     */
    public static function enumCases(array $values): array
    {
        $cases = [];
        foreach ($values as $value) {
            $name = self::studly(1 === preg_match('/^[A-Za-z]/', $value) ? $value : 'value '.$value);
            if ('class' === strtolower($name)) {
                $name .= 'Value';
            }
            $candidate = $name;
            for ($suffix = 2; in_array($candidate, $cases, true); ++$suffix) {
                $candidate = $name.$suffix;
            }
            $cases[] = $candidate;
        }

        return $cases;
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
