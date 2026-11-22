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

namespace KonradMichalik\Typo3Outbound\Mapping;

use KonradMichalik\Typo3Outbound\Value\{Decimal, Iri};

use function get_object_vars;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;

/**
 * Normalizer.
 *
 * Turns request DTOs into plain arrays. Enums become their backing value, dates
 * use the connection's format, `Decimal` and `Iri` their string value. For query
 * strings, booleans become "true"/"false" (or "1"/"0") and null members vanish.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Normalizer
{
    public function __construct(
        private string $dateFormat = \DateTimeInterface::ATOM,
        private bool $booleansAsStrings = true,
    ) {}

    /**
     * Normalization for JSON bodies: scalars keep their type, nulls are kept.
     */
    public function forBody(mixed $value): mixed
    {
        return $this->normalize($value, false);
    }

    /**
     * Normalization for query strings: every leaf is a string, nulls are removed.
     */
    public function forQuery(mixed $value): mixed
    {
        return $this->normalize($value, true);
    }

    private function normalize(mixed $value, bool $query): mixed
    {
        return match (true) {
            null === $value => null,
            is_bool($value) => $query ? $this->bool($value) : $value,
            $value instanceof \BackedEnum => $query ? (string) $value->value : $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format($this->dateFormat),
            $value instanceof Decimal, $value instanceof Iri => $value->value,
            $value instanceof \JsonSerializable => $this->normalize($value->jsonSerialize(), $query),
            $value instanceof \Stringable => (string) $value,
            is_object($value) => $this->members(get_object_vars($value), $query),
            is_array($value) => $this->members($value, $query),
            default => $query && is_scalar($value) ? (string) $value : $value,
        };
    }

    /**
     * @param array<array-key, mixed> $members
     *
     * @return array<array-key, mixed>
     */
    private function members(array $members, bool $query): array
    {
        $normalized = [];
        foreach ($members as $key => $member) {
            $value = $this->normalize($member, $query);
            if ($query && (null === $value || [] === $value)) {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function bool(bool $value): string
    {
        if ($this->booleansAsStrings) {
            return $value ? 'true' : 'false';
        }

        return $value ? '1' : '0';
    }
}
