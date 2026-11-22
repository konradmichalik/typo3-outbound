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

use Symfony\Component\RateLimiter\LimiterStateInterface;
use Symfony\Component\RateLimiter\Storage\StorageInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

use function is_string;
use function serialize;
use function sha1;
use function unserialize;

/**
 * RateLimiterStorage.
 *
 * symfony/rate-limiter storage on the caching framework.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RateLimiterStorage implements StorageInterface
{
    public function __construct(
        private FrontendInterface $cache,
    ) {}

    public function save(LimiterStateInterface $limiterState): void
    {
        $this->cache->set('limiter_'.sha1($limiterState->getId()), serialize($limiterState), [], $limiterState->getExpirationTime());
    }

    public function fetch(string $limiterStateId): ?LimiterStateInterface
    {
        $data = $this->cache->get('limiter_'.sha1($limiterStateId));
        if (!is_string($data)) {
            return null;
        }
        $state = unserialize($data, ['allowed_classes' => [\Symfony\Component\RateLimiter\Policy\SlidingWindow::class, \Symfony\Component\RateLimiter\Policy\Window::class, \Symfony\Component\RateLimiter\Policy\TokenBucket::class, \Symfony\Component\RateLimiter\Policy\Rate::class]]);

        return $state instanceof LimiterStateInterface ? $state : null;
    }

    public function delete(string $limiterStateId): void
    {
        $this->cache->remove('limiter_'.sha1($limiterStateId));
    }
}
