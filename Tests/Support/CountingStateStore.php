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

namespace KonradMichalik\Typo3Outbound\Tests\Support;

use KonradMichalik\Typo3Outbound\Resilience\{InMemoryStateStore, StateStore};

/**
 * CountingStateStore.
 *
 * Records every store access, so tests can assert how often a call touches the backend.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CountingStateStore implements StateStore
{
    /** @var list<array{0: string, 1: string}> Operation and key */
    public array $operations = [];

    public function __construct(
        private readonly StateStore $inner = new InMemoryStateStore(),
    ) {}

    public function get(string $key): ?array
    {
        $this->operations[] = ['get', $key];

        return $this->inner->get($key);
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->operations[] = ['set', $key];
        $this->inner->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        $this->operations[] = ['delete', $key];
        $this->inner->delete($key);
    }

    public function count(string $operation, string $keyPrefix): int
    {
        return count(array_filter($this->operations, static fn(array $entry): bool => $entry[0] === $operation && str_starts_with($entry[1], $keyPrefix)));
    }
}
