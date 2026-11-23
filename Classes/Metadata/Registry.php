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

use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use ReflectionClass;

use function array_keys;
use function serialize;
use function sprintf;
use function unserialize;

/**
 * Registry.
 *
 * All connections and endpoints known to the installation. Built once while the
 * container is compiled and stored as serialized chunks per interface, so a
 * request only materializes the metadata of the interfaces it actually calls.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Registry
{
    /** @var array<string, ConnectionMetadata> */
    private array $connections = [];

    /** @var array<class-string, list<EndpointMetadata>> */
    private array $endpoints = [];

    /**
     * @param array<string, string>       $connectionChunks Connection name => serialized ConnectionMetadata
     * @param array<class-string, string> $endpointChunks   Interface => serialized list<EndpointMetadata>
     * @param array<class-string, string> $interfaces       Interface => connection name
     */
    public function __construct(
        private readonly array $connectionChunks,
        private readonly array $endpointChunks,
        private readonly array $interfaces,
    ) {}

    /**
     * @param list<class-string> $interfaces
     */
    public static function build(array $interfaces, MetadataFactory $factory = new MetadataFactory()): self
    {
        $connections = [];
        $connectionInterfaces = [];
        $endpoints = [];
        $owners = [];
        $resourcesPerBase = [];
        foreach ($interfaces as $interfaceName) {
            $interface = new ReflectionClass($interfaceName);
            $base = $factory->baseOf($interface);
            $connection = $connections[$base->getName()] ??= $factory->connection($base);
            $other = $connectionInterfaces[$connection->name] ?? null;
            if (null !== $other && $other !== $base->getName()) {
                throw new InvalidDefinition(sprintf('Connection name "%s" is used by %s and %s. Set #[Connection(name: ...)] on one of them.', $connection->name, $other, $base->getName()), 1794700024);
            }
            $connectionInterfaces[$connection->name] = $base->getName();
            if ($base->getName() !== $interfaceName) {
                $resourcesPerBase[$base->getName()][] = $interfaceName;
            }
            $endpoints[$interfaceName] = $factory->endpoints($interface, $connection);
            $owners[$interfaceName] = $connection->name;
        }
        foreach ($resourcesPerBase as $baseName => $resources) {
            if ([] !== ($endpoints[$baseName] ?? []) || [] !== $factory->endpoints(new ReflectionClass($baseName), $connections[$baseName])) {
                throw new InvalidDefinition(sprintf('%s declares endpoints and is extended by resources (%s). Move its endpoints into resource interfaces, otherwise every resource inherits them.', $baseName, implode(', ', $resources)), 1794700025);
            }
        }

        $connectionChunks = [];
        foreach ($connections as $connection) {
            $tracksEtags = false;
            foreach ($endpoints as $list) {
                foreach ($list as $endpoint) {
                    $tracksEtags = $tracksEtags || ($endpoint->connection === $connection->name && $endpoint->verb->ifMatch);
                }
            }
            $connectionChunks[$connection->name] = serialize($tracksEtags ? $connection->withEtagTracking() : $connection);
        }
        $endpointChunks = [];
        foreach ($endpoints as $interfaceName => $list) {
            $endpointChunks[$interfaceName] = serialize($list);
        }

        return new self($connectionChunks, $endpointChunks, $owners);
    }

    /**
     * @return array{connections: array<string, string>, endpoints: array<class-string, string>, interfaces: array<class-string, string>}
     */
    public function export(): array
    {
        return ['connections' => $this->connectionChunks, 'endpoints' => $this->endpointChunks, 'interfaces' => $this->interfaces];
    }

    /**
     * @param array{connections: array<string, string>, endpoints: array<class-string, string>, interfaces: array<class-string, string>} $export
     */
    public static function fromExport(array $export): self
    {
        return new self($export['connections'], $export['endpoints'], $export['interfaces']);
    }

    public function connection(string $name): ConnectionMetadata
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        if (!isset($this->connectionChunks[$name])) {
            throw new InvalidDefinition(sprintf('Unknown connection "%s".', $name), 1794700026);
        }
        $connection = unserialize($this->connectionChunks[$name]);
        if (!$connection instanceof ConnectionMetadata) {
            throw new InvalidDefinition(sprintf('Corrupt metadata for connection "%s".', $name), 1794700027);
        }

        return $this->connections[$name] = $connection;
    }

    /**
     * @param class-string $interface
     */
    public function connectionOf(string $interface): ConnectionMetadata
    {
        return $this->connection($this->interfaces[$interface] ?? throw new InvalidDefinition(sprintf('%s is not registered with Outbound.', $interface), 1794700028));
    }

    /**
     * @param class-string $interface
     *
     * @return list<EndpointMetadata>
     */
    public function endpointsOf(string $interface): array
    {
        if (isset($this->endpoints[$interface])) {
            return $this->endpoints[$interface];
        }
        /** @var list<EndpointMetadata> $list */
        $list = unserialize($this->endpointChunks[$interface] ?? throw new InvalidDefinition(sprintf('%s is not registered with Outbound.', $interface), 1794700029));

        return $this->endpoints[$interface] = $list;
    }

    /**
     * @param class-string $interface
     */
    public function endpoint(string $interface, string $method): EndpointMetadata
    {
        foreach ($this->endpointsOf($interface) as $endpoint) {
            if ($endpoint->method === $method) {
                return $endpoint;
            }
        }

        throw new InvalidDefinition(sprintf('%s::%s is not an endpoint.', $interface, $method), 1794700030);
    }

    /**
     * @return list<class-string>
     */
    public function interfaces(): array
    {
        return array_keys($this->interfaces);
    }

    /**
     * @return list<string>
     */
    public function connectionNames(): array
    {
        return array_keys($this->connectionChunks);
    }
}
