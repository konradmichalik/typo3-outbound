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

namespace KonradMichalik\Typo3Outbound\Cache;

use function array_intersect;

/**
 * InMemoryResponseCache.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class InMemoryResponseCache implements ResponseCache
{
    /** @var array<string, array{entry: CacheEntry, tags: list<string>}> */
    private array $entries = [];

    public function get(string $key): ?CacheEntry
    {
        return $this->entries[$key]['entry'] ?? null;
    }

    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void
    {
        $this->entries[$key] = ['entry' => $entry, 'tags' => $tags];
    }

    public function flushTags(array $tags): void
    {
        foreach ($this->entries as $key => $item) {
            if ([] !== array_intersect($item['tags'], $tags)) {
                unset($this->entries[$key]);
            }
        }
    }
}
