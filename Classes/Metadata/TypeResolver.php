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

namespace KonradMichalik\Typo3Outbound\Metadata;

use PhpToken;
use ReflectionClass;

use function count;
use function file_get_contents;
use function in_array;
use function is_string;
use function ltrim;
use function preg_replace_callback;
use function str_contains;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_TRAIT;
use const T_USE;

/**
 * TypeResolver.
 *
 * Resolves class names in PHPDoc type signatures (e.g. `list<PetDto>`) to fully
 * qualified names, using the namespace and the `use` imports of the file that
 * declares the interface.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class TypeResolver
{
    private const BUILTIN = [
        'int', 'integer', 'string', 'float', 'double', 'bool', 'boolean', 'array', 'list', 'iterable', 'mixed', 'null',
        'true', 'false', 'object', 'callable', 'void', 'never', 'scalar', 'array-key', 'non-empty-string',
        'non-empty-list', 'non-empty-array', 'positive-int', 'negative-int', 'non-negative-int', 'non-positive-int',
        'numeric-string', 'class-string', 'lowercase-string', 'self', 'static',
    ];

    /** @var array<string, array{namespace: string, imports: array<string, string>}> */
    private array $contexts = [];

    /**
     * @param ReflectionClass<object> $declaringClass
     */
    public function resolve(string $signature, ReflectionClass $declaringClass): string
    {
        $context = $this->context($declaringClass);

        // The first alternative keeps the bounds of an integer range (`int<0, max>`) out of reach:
        // they are keywords, not class names.
        return (string) preg_replace_callback(
            '/int<\s*(?:-?\d+|min)\s*,\s*(?:-?\d+|max)\s*>|(?<![\w\\\\-])(\\\\?[A-Za-z_][\w\\\\-]*)(?![\w\\\\-]*\??\s*:)/',
            fn (array $match): string => isset($match[1]) ? $this->qualify($match[1], $context) : $match[0],
            $signature,
        );
    }

    /**
     * @param array{namespace: string, imports: array<string, string>} $context
     */
    private function qualify(string $name, array $context): string
    {
        // Class names have no hyphens: `non-empty-list` and `class-string` are PHPDoc types.
        if (in_array(strtolower($name), self::BUILTIN, true) || str_contains($name, '-')) {
            return $name;
        }
        if ('\\' === $name[0]) {
            return ltrim($name, '\\');
        }
        $head = explode('\\', $name)[0];
        $alias = strtolower($head);
        if (isset($context['imports'][$alias])) {
            return $context['imports'][$alias].substr($name, strlen($head));
        }

        return '' === $context['namespace'] ? $name : $context['namespace'].'\\'.$name;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return array{namespace: string, imports: array<string, string>}
     */
    private function context(ReflectionClass $class): array
    {
        $file = $class->getFileName();
        if (!is_string($file)) {
            return ['namespace' => $class->getNamespaceName(), 'imports' => []];
        }

        return $this->contexts[$file] ??= $this->parse((string) file_get_contents($file), $class->getNamespaceName());
    }

    /**
     * @return array{namespace: string, imports: array<string, string>}
     */
    private function parse(string $code, string $namespace): array
    {
        $tokens = PhpToken::tokenize($code);
        $imports = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                break;
            }
            if ($token->is(T_NAMESPACE)) {
                continue;
            }
            if (!$token->is(T_USE)) {
                continue;
            }
            $statement = '';
            for (++$i; $i < $count && ';' !== $tokens[$i]->text; ++$i) {
                $statement .= $tokens[$i]->text;
            }
            foreach ($this->expand(trim($statement)) as $alias => $fqcn) {
                $imports[$alias] = $fqcn;
            }
        }

        return ['namespace' => $namespace, 'imports' => $imports];
    }

    /**
     * Expands `A\B`, `A\B as C` and `A\{B, C as D}`; skips function and const imports.
     *
     * @return array<string, string>
     */
    private function expand(string $statement): array
    {
        if (str_starts_with($statement, 'function ') || str_starts_with($statement, 'const ')) {
            return [];
        }
        $statement = (string) preg_replace('/\s+/', ' ', $statement);
        $parts = [];
        if (false !== ($brace = strpos($statement, '{'))) {
            $prefix = trim(substr($statement, 0, $brace));
            foreach (explode(',', trim(substr($statement, $brace + 1), '} ')) as $item) {
                $parts[] = $prefix.trim($item);
            }
        } else {
            $parts[] = $statement;
        }

        $imports = [];
        foreach ($parts as $part) {
            $segments = explode(' as ', $part);
            $fqcn = ltrim(trim($segments[0]), '\\');
            $alias = isset($segments[1]) ? trim($segments[1]) : substr($fqcn, (int) strrpos('\\'.$fqcn, '\\'));
            $imports[strtolower($alias)] = $fqcn;
        }

        return $imports;
    }
}
