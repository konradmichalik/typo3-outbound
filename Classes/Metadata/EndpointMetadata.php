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

use KonradMichalik\Typo3Outbound\Attribute\{Cache, Endpoint, InvalidatesCache, MapKeys, OnStatus, Paginate, Retry, Timeout};

use function array_filter;
use function array_values;

/**
 * EndpointMetadata.
 *
 * The effective configuration of one endpoint after the precedence rules
 * (method over resource over connection) have been applied.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class EndpointMetadata
{
    /**
     * @param class-string            $interface
     * @param list<ParameterMetadata> $parameters
     * @param list<OnStatus>          $onStatus
     * @param array<string, string>   $origins    Attribute name => interface or method it was taken from
     */
    public function __construct(
        public string $key,
        public string $interface,
        public string $method,
        public string $connection,
        public Endpoint $verb,
        public string $path,
        public array $parameters,
        public ReturnType $returnType,
        public bool $idempotent,
        public ?Retry $retry = null,
        public ?Timeout $timeout = null,
        public ?Cache $cache = null,
        public ?MapKeys $mapKeys = null,
        public ?Paginate $paginate = null,
        public array $onStatus = [],
        public bool $healthCheck = false,
        public array $origins = [],
        public ?InvalidatesCache $invalidates = null,
    ) {}

    /**
     * @return list<ParameterMetadata>
     */
    public function parametersOf(ParameterKind $kind): array
    {
        return array_values(array_filter($this->parameters, static fn (ParameterMetadata $parameter): bool => $parameter->kind === $kind));
    }

    public function httpMethod(): string
    {
        return $this->verb->method();
    }
}
