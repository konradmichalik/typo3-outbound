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

namespace KonradMichalik\Typo3Outbound\Runtime;

use KonradMichalik\Typo3Outbound\Resilience\{RateLimitGuard, StateStore};
use Psr\Log\LoggerInterface;

use function hash;

/**
 * ConventionObserver.
 *
 * Watches response headers that concern the integration rather than the data:
 * announced quotas and the Sunset (RFC 8594) and Deprecation (RFC 9745) headers.
 * A deprecation is logged once per endpoint and process and kept for
 * `outbound:health`.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ConventionObserver
{
    private const DEPRECATION_TTL = 604_800;

    /** @var array<string, true> */
    private array $reported = [];

    public function __construct(
        private readonly RateLimitGuard $rateLimit,
        private readonly StateStore $store,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, list<string>> $headers
     */
    public function observe(Execution $execution, array $headers): void
    {
        $this->rateLimit->record($execution->connection, $headers);

        $sunset = $headers['sunset'][0] ?? null;
        $deprecation = $headers['deprecation'][0] ?? null;
        if ((null === $sunset && null === $deprecation) || isset($this->reported[$execution->endpoint->key])) {
            return;
        }
        $this->reported[$execution->endpoint->key] = true;
        $this->logger->warning('Endpoint {endpoint} is deprecated by the API (Deprecation: {deprecation}, Sunset: {sunset}).', [
            'endpoint' => $execution->endpoint->key,
            'deprecation' => $deprecation ?? '-',
            'sunset' => $sunset ?? '-',
        ]);
        $this->store->set(self::deprecationKey($execution->endpoint->key), [
            'endpoint' => $execution->endpoint->key,
            'connection' => $execution->connection->name,
            'deprecation' => $deprecation,
            'sunset' => $sunset,
        ], self::DEPRECATION_TTL);
    }

    public static function deprecationKey(string $endpoint): string
    {
        return 'deprecation_'.hash('xxh128', $endpoint);
    }
}
