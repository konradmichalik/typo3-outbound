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

namespace KonradMichalik\Typo3Outbound\Metadata;

use KonradMichalik\Typo3Outbound\Attribute\Auth\Authentication;
use KonradMichalik\Typo3Outbound\Attribute\{Cache, CircuitBreaker, Connection, RateLimit, Retry, Timeout};
use KonradMichalik\Typo3Outbound\Value\SiteSetting;

/**
 * ConnectionMetadata.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ConnectionMetadata
{
    /**
     * @param class-string $interface
     */
    public function __construct(
        public string $name,
        public string $interface,
        public Connection $definition,
        public ?Authentication $auth = null,
        public ?Retry $retry = null,
        public ?Timeout $timeout = null,
        public ?Cache $cache = null,
        public ?RateLimit $rateLimit = null,
        public ?CircuitBreaker $circuitBreaker = null,
        public bool $tracksEtags = false,
    ) {}

    /**
     * Key for state that belongs to the API behind the connection, such as the circuit breaker
     * or a quota. A base URI from a site setting points to a different API per site, so those
     * sites must not share it; one base URI is one API for all sites.
     */
    public function stateScope(?string $site): string
    {
        return $this->definition->baseUri instanceof SiteSetting && null !== $site ? $this->name.'@'.$site : $this->name;
    }

    /**
     * Set when an endpoint of the connection uses `ifMatch`, so ETags of its GET responses are remembered.
     */
    public function withEtagTracking(): self
    {
        return new self($this->name, $this->interface, $this->definition, $this->auth, $this->retry, $this->timeout, $this->cache, $this->rateLimit, $this->circuitBreaker, true);
    }
}
