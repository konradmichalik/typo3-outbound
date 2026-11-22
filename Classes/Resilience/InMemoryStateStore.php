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

namespace KonradMichalik\Typo3Outbound\Resilience;

/**
 * InMemoryStateStore.
 *
 * Per-process store, used in tests and as fallback outside TYPO3. Expiry is left
 * to the consumers that store timestamps.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class InMemoryStateStore implements StateStore
{
    /** @var array<string, array<string, mixed>> */
    private array $entries = [];

    public function get(string $key): ?array
    {
        return $this->entries[$key] ?? null;
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->entries[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->entries[$key]);
    }
}
