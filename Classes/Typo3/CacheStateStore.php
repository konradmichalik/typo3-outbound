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

namespace KonradMichalik\Typo3Outbound\Typo3;

use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

use function hash;
use function is_array;

/**
 * CacheStateStore.
 *
 * Shared state on the caching framework (cache "outbound_state"). Use a shared
 * backend such as Redis when several servers run the installation.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CacheStateStore implements StateStore
{
    public function __construct(
        private FrontendInterface $cache,
    ) {}

    public function get(string $key): ?array
    {
        /** @var array<string, mixed>|false $value */
        $value = $this->cache->get(hash('xxh128', $key));

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->cache->set(hash('xxh128', $key), $value, [], $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->remove(hash('xxh128', $key));
    }
}
