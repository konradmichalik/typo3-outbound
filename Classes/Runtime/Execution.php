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

use KonradMichalik\Typo3Outbound\Http\RequestSpec;
use KonradMichalik\Typo3Outbound\Metadata\{ConnectionMetadata, EndpointMetadata};
use KonradMichalik\Typo3Outbound\Transport\TransportOptions;

/**
 * Execution.
 *
 * One logical call: the same request, idempotency key and credentials across
 * all retries, until the credentials are refreshed after a 401.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Execution
{
    private bool $credentialsRefreshed = false;

    /**
     * @param array<string, mixed>                         $arguments
     * @param \Closure(): array{0: RequestSpec, 1: string}  $rebuild
     */
    public function __construct(
        public readonly EndpointMetadata $endpoint,
        public readonly ConnectionMetadata $connection,
        public readonly array $arguments,
        public RequestSpec $request,
        public readonly string $identity,
        public readonly TransportOptions $options,
        private readonly \Closure $rebuild,
    ) {}

    /**
     * Rebuilds the request with fresh credentials, at most once per call.
     */
    public function refreshCredentials(): bool
    {
        if ($this->credentialsRefreshed) {
            return false;
        }
        $headers = $this->request->headers;
        [$request] = ($this->rebuild)();
        foreach (['If-None-Match', 'If-Match'] as $conditional) {
            if (isset($headers[$conditional])) {
                $request = $request->withHeaders([$conditional => $headers[$conditional]]);
            }
        }
        $this->request = $request;
        $this->credentialsRefreshed = true;

        return true;
    }
}
