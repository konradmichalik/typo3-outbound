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

namespace KonradMichalik\Typo3Outbound;

use KonradMichalik\Outbound\Cache\{CacheEntry, ResponseCache};
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

use function is_array;

/**
 * CacheResponseCache.
 *
 * Response cache on the caching framework (cache "outbound"). Entries are arrays
 * of scalars holding the raw JSON body, never serialized objects.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CacheResponseCache implements ResponseCache
{
    public function __construct(
        private FrontendInterface $cache,
    ) {}

    public function get(string $key): ?CacheEntry
    {
        $data = $this->cache->get($key);

        return is_array($data) ? CacheEntry::fromArray($data) : null;
    }

    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void
    {
        $this->cache->set($key, $entry->toArray(), $tags, $lifetime);
    }

    public function flushTags(array $tags): void
    {
        $this->cache->flushByTags($tags);
    }
}
