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

use KonradMichalik\Typo3Outbound\Context\{CallContext, ExecutionContext};
use KonradMichalik\Typo3Outbound\Exception\ApiRateLimited;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Support\Clock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\{InMemoryStorage, StorageInterface};

use function ceil;
use function is_int;
use function is_numeric;
use function max;
use function sprintf;
use function strtolower;

/**
 * RateLimitGuard.
 *
 * Applies the connection's #[RateLimit] with symfony/rate-limiter and honours a
 * quota the server announced in its rate-limit headers. A web request fails
 * immediately when a limit is exhausted; CLI waits.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RateLimitGuard
{
    private const QUOTA_RECHECK = 5.0;

    /** @var array<string, RateLimiterFactory> */
    private array $factories = [];

    /** @var array<string, array{resetAt: ?int, readAt: float}> */
    private array $quotas = [];

    public function __construct(
        private readonly StateStore $store,
        private readonly ExecutionContext $context,
        private readonly Clock $clock,
        private readonly StorageInterface $storage = new InMemoryStorage(),
        private readonly ?CallContext $calls = null,
    ) {}

    public function acquire(ConnectionMetadata $connection, string $endpoint): void
    {
        $this->awaitServerQuota($connection, $endpoint);
        $limit = $connection->rateLimit;
        if (null === $limit) {
            return;
        }
        $scope = $this->scope($connection);
        $factory = $this->factories[$scope] ??= new RateLimiterFactory(
            ['id' => 'outbound_'.$scope, 'policy' => 'sliding_window', 'limit' => $limit->limit, 'interval' => $limit->per],
            $this->storage,
        );
        $limiter = $factory->create($scope);
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            $result = $limiter->consume();
            if ($result->isAccepted()) {
                return;
            }
            $wait = max(0.0, $result->getRetryAfter()->getTimestamp() - $this->clock->now());
            if ($this->context->isWeb()) {
                throw new ApiRateLimited(sprintf('Connection %s: local rate limit of %d per %s reached.', $connection->name, $limit->limit, $limit->per), 1794700100, $endpoint, null, null, (int) ceil($wait));
            }
            $this->clock->sleep($wait);
        }

        throw new ApiRateLimited(sprintf('Connection %s: local rate limit still exhausted after waiting.', $connection->name), 1794700101, $endpoint);
    }

    /**
     * Records the quota announced by the server, if the response carries it.
     *
     * @param array<string, list<string>> $headers
     */
    public function record(ConnectionMetadata $connection, array $headers): void
    {
        $names = $connection->definition->rateLimitHeaders;
        $remaining = $headers[strtolower($names['remaining'] ?? '')][0] ?? null;
        if (!is_numeric($remaining) || (int) $remaining > 0) {
            return;
        }
        $reset = $headers[strtolower($names['reset'] ?? '')][0] ?? null;
        $now = (int) $this->clock->now();
        $resetAt = is_numeric($reset) ? ((int) $reset > 1_000_000_000 ? (int) $reset : $now + (int) $reset) : $now + 60;
        $this->store->set('quota_'.$this->scope($connection), ['resetAt' => $resetAt], max(1, $resetAt - $now));
        $this->quotas[$this->scope($connection)] = ['resetAt' => $resetAt, 'readAt' => $this->clock->now()];
    }

    private function awaitServerQuota(ConnectionMetadata $connection, string $endpoint): void
    {
        $known = $this->quotas[$this->scope($connection)] ?? null;
        if (null === $known || $this->clock->now() - $known['readAt'] >= self::QUOTA_RECHECK) {
            $state = $this->store->get('quota_'.$this->scope($connection));
            $known = ['resetAt' => is_int($state['resetAt'] ?? null) ? $state['resetAt'] : null, 'readAt' => $this->clock->now()];
            $this->quotas[$this->scope($connection)] = $known;
        }
        $resetAt = $known['resetAt'];
        if (null === $resetAt || $resetAt <= (int) $this->clock->now()) {
            return;
        }
        $wait = $resetAt - $this->clock->now();
        if ($this->context->isWeb()) {
            throw new ApiRateLimited(sprintf('Connection %s: the API announced an exhausted quota until %d.', $connection->name, $resetAt), 1794700102, $endpoint, null, null, (int) ceil($wait));
        }
        $this->clock->sleep($wait);
    }

    private function scope(ConnectionMetadata $connection): string
    {
        return $connection->stateScope($this->calls?->scope());
    }
}
