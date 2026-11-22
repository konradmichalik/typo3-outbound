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

use KonradMichalik\Typo3Outbound\Metadata\{ConnectionMetadata, EndpointMetadata, Registry, ReturnKind};
use KonradMichalik\Typo3Outbound\Value\Deferred;

use function array_key_exists;

/**
 * EndpointInvoker.
 *
 * Entry point of every generated proxy method.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class EndpointInvoker
{
    public function __construct(
        private Registry $registry,
        private CallFactory $calls,
        private CacheLayer $layer,
        private ResultMapper $results,
        private Paginator $paginator,
    ) {}

    /**
     * @param class-string $interface
     * @param list<mixed>  $arguments Positional arguments as passed to the proxy method
     */
    public function call(string $interface, string $method, array $arguments): mixed
    {
        $endpoint = $this->registry->endpoint($interface, $method);
        $connection = $this->registry->connection($endpoint->connection);
        $named = [];
        foreach ($endpoint->parameters as $parameter) {
            $named[$parameter->name] = array_key_exists($parameter->position, $arguments) ? $arguments[$parameter->position] : null;
        }

        return match ($endpoint->returnType->kind) {
            ReturnKind::Iterable => $this->paginator->iterate($endpoint, $connection, $named),
            ReturnKind::Deferred => $this->deferred($endpoint, $connection, $named),
            default => $this->execute($endpoint, $connection, $named),
        };
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function execute(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments): mixed
    {
        $execution = $this->calls->create($endpoint, $connection, $arguments);

        return $this->results->map($this->layer->finish($this->layer->begin($execution)), $execution);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return Deferred<mixed>
     */
    private function deferred(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments): Deferred
    {
        $execution = $this->calls->create($endpoint, $connection, $arguments);
        $pending = $this->layer->begin($execution);

        return new Deferred(fn(): mixed => $this->results->map($this->layer->finish($pending), $execution));
    }
}
