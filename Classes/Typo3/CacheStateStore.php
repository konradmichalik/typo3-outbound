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

use function is_array;
use function sha1;

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
        $value = $this->cache->get(sha1($key));

        /** @var array<string, mixed>|null */
        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value, int $ttl): void
    {
        $this->cache->set(sha1($key), $value, [], $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->remove(sha1($key));
    }
}
