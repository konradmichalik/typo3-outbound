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

use KonradMichalik\Typo3Outbound\Exception\ApiUnavailable;
use KonradMichalik\Typo3Outbound\Context\CallContext;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Support\Clock;
use Psr\Log\LoggerInterface;

use function is_int;
use function sprintf;

/**
 * CircuitBreaker.
 *
 * The success path only reads the state; it is written on failures and on the
 * transition back to closed. A shared store backend makes the state visible to
 * all PHP workers.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CircuitBreaker
{
    /** @var array<string, bool> Connection name => the state read in guard() showed failures */
    private array $dirty = [];

    public function __construct(
        private readonly StateStore $store,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
        private readonly ?CallContext $context = null,
    ) {}

    public function guard(ConnectionMetadata $connection, string $endpoint): void
    {
        if (null === $connection->circuitBreaker) {
            return;
        }
        $state = $this->store->get($this->key($connection));
        $this->dirty[$this->scope($connection)] = null !== $state;
        $openUntil = $state['openUntil'] ?? null;
        if (is_int($openUntil) && $openUntil > (int) $this->clock->now()) {
            throw new ApiUnavailable(sprintf('Connection %s is unavailable, circuit breaker open until %d.', $connection->name, $openUntil), 1794700090, $endpoint, null, null, $openUntil);
        }
    }

    public function success(ConnectionMetadata $connection): void
    {
        if (null === $connection->circuitBreaker) {
            return;
        }
        // guard() already read the state for this call. Only a connection with recorded
        // failures needs a write; the normal case touches the store once per call.
        if ($this->dirty[$this->scope($connection)] ?? true) {
            $this->store->delete($this->key($connection));
            $this->dirty[$this->scope($connection)] = false;
        }
    }

    public function failure(ConnectionMetadata $connection): void
    {
        $settings = $connection->circuitBreaker;
        if (null === $settings) {
            return;
        }
        $state = $this->store->get($this->key($connection)) ?? [];
        $failures = (is_int($state['failures'] ?? null) ? $state['failures'] : 0) + 1;
        $value = ['failures' => $failures];
        if ($failures >= $settings->failureThreshold) {
            $value['openUntil'] = (int) $this->clock->now() + $settings->cooldown;
            $this->logger->warning('Circuit breaker of connection {connection} opened after {failures} failures.', ['connection' => $connection->name, 'failures' => $failures]);
        }
        $this->store->set($this->key($connection), $value, $settings->cooldown * 10);
        $this->dirty[$this->scope($connection)] = true;
    }

    private function key(ConnectionMetadata $connection): string
    {
        return 'breaker_'.$this->scope($connection);
    }

    private function scope(ConnectionMetadata $connection): string
    {
        return $connection->stateScope($this->context?->siteIdentifier());
    }
}
