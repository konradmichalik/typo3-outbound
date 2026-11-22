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

namespace KonradMichalik\Typo3Outbound\Command;

use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use KonradMichalik\Typo3Outbound\Metadata\{EndpointMetadata, Registry};

use function sprintf;

/**
 * EndpointLocator.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class EndpointLocator
{
    public function __construct(
        private Registry $registry,
    ) {}

    /**
     * @return list<EndpointMetadata>
     */
    public function all(?string $connection = null): array
    {
        $endpoints = [];
        foreach ($this->registry->interfaces() as $interface) {
            foreach ($this->registry->endpointsOf($interface) as $endpoint) {
                if (null === $connection || $endpoint->connection === $connection) {
                    $endpoints[] = $endpoint;
                }
            }
        }

        return $endpoints;
    }

    public function byKey(string $key): EndpointMetadata
    {
        foreach ($this->all() as $endpoint) {
            if ($endpoint->key === $key) {
                return $endpoint;
            }
        }

        throw new InvalidDefinition(sprintf('Unknown endpoint "%s". Run outbound:debug for the list.', $key), 1794700200);
    }
}
