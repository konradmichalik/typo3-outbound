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

use KonradMichalik\Typo3Outbound\Proxy\ProxyStore;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;

use function str_starts_with;
use function substr;

/**
 * PhpCacheProxyStore.
 *
 * Proxies as PHP code in the cache "outbound_proxies" (group system), flushed
 * together with the DI container on deployment.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PhpCacheProxyStore implements ProxyStore
{
    public function __construct(
        private PhpFrontend $cache,
    ) {}

    public function has(string $identifier): bool
    {
        return $this->cache->has($identifier);
    }

    public function save(string $identifier, string $code): void
    {
        $this->cache->set($identifier, str_starts_with($code, '<?php') ? substr($code, 5) : $code);
    }

    public function load(string $identifier): void
    {
        $this->cache->requireOnce($identifier);
    }
}
