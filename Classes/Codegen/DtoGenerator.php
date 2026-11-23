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

use InvalidArgumentException;

use function array_is_list;
use function array_key_exists;
use function get_debug_type;
use function is_array;
use function sprintf;

/**
 * DtoGenerator.
 *
 * Generates DTO scaffolds from a sample response. A single sample cannot prove
 * nullability or union types: null values become optional `mixed` properties,
 * empty lists are typed `list<string>` and marked for review. The result is
 * meant to be checked and committed, never generated at runtime.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class DtoGenerator
{
    /** @var array<string, string> */
    private array $files = [];

    /**
     * @return array<string, string> Class name => file content
     */
    public function generate(string $namespace, string $class, mixed $sample, bool $camelCase = false): array
    {
        $this->files = [];
        if (is_array($sample) && array_is_list($sample)) {
            $sample = $this->merge($sample);
        }
        if (!is_array($sample)) {
            throw new InvalidArgumentException('The sample must be a JSON object or a list of objects.', 1794700210);
        }
        $this->object($namespace, $class, $sample, $camelCase);

        return $this->files;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function object(string $namespace, string $class, array $data, bool $camelCase): void
    {
        $properties = [];
        foreach ($data as $key => $value) {
            $name = Names::property((string) $key, $camelCase);
            [$type, $doc] = $this->type($namespace, $class, (string) $key, $value, $camelCase);
            $properties[] = ['name' => $name, 'type' => $type, 'doc' => $doc, 'optional' => null === $value];
        }
        $this->files[$class] = ClassFile::dto($namespace, $class, $properties, 'Generated from a sample response by outbound:make:dto. Review nullability and list types.');
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function type(string $namespace, string $parent, string $key, mixed $value, bool $camelCase): array
    {
        if (is_array($value) && !array_is_list($value)) {
            $class = Names::studly($key).'Dto';
            $this->object($namespace, $class === $parent ? $parent.$class : $class, $value, $camelCase);

            return [$class, null];
        }
        if (is_array($value)) {
            if ([] === $value) {
                return ['array', 'list<string> TODO: the sample list was empty, verify the item type'];
            }
            $first = $this->merge($value);
            if (is_array($first)) {
                $class = Names::studly(Names::singular($key)).'Dto';
                $this->object($namespace, $class, $first, $camelCase);

                return ['array', sprintf('list<%s>', $class)];
            }

            return ['array', sprintf('list<%s>', $this->scalar($value[0]))];
        }

        return null === $value ? ['mixed', null] : [$this->scalar($value), null];
    }

    /**
     * Merges list items into one representative item, so keys that only some items have are kept.
     *
     * @param list<mixed> $items
     */
    private function merge(array $items): mixed
    {
        $merged = null;
        foreach ($items as $item) {
            if (!is_array($item) || array_is_list($item)) {
                return $items[0] ?? null;
            }
            $merged ??= [];
            foreach ($item as $key => $value) {
                if (!array_key_exists($key, $merged) || null === $merged[$key]) {
                    $merged[$key] = $value;
                }
            }
        }

        return $merged;
    }

    private function scalar(mixed $value): string
    {
        return match (get_debug_type($value)) {
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            default => 'string',
        };
    }
}
