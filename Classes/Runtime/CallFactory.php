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

use KonradMichalik\Typo3Outbound\Auth\Authenticator;
use KonradMichalik\Typo3Outbound\Context\ExecutionContext;
use KonradMichalik\Typo3Outbound\Http\RequestBuilder;
use KonradMichalik\Typo3Outbound\Metadata\{ConnectionMetadata, EndpointMetadata};
use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use KonradMichalik\Typo3Outbound\Transport\TransportOptions;

use function bin2hex;
use function chr;
use function is_string;
use function ord;
use function random_bytes;
use function str_split;
use function vsprintf;

/**
 * CallFactory.
 *
 * Creates the execution of one logical call: credentials, request, idempotency
 * key and If-Match header. TLS verification can only be switched off in the
 * Development context.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CallFactory
{
    public function __construct(
        private RequestBuilder $builder,
        private Authenticator $authenticator,
        private StateStore $store,
        private ExecutionContext $context,
        private Settings $settings,
    ) {}

    /**
     * @param array<string, mixed>  $arguments
     * @param array<string, scalar> $extraQuery
     */
    public function create(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments, array $extraQuery = [], ?string $url = null): Execution
    {
        $definition = $connection->definition;
        $options = new TransportOptions(
            maxDuration: 0.0,
            maxResponseBytes: $definition->maxResponseBytes ?? $this->settings->maxResponseBytes,
            verifyTls: $definition->verifyTls || !$this->context->isDevelopment(),
            allowPrivateNetwork: $definition->allowPrivateNetwork,
        );

        $extraHeaders = [];
        if ($endpoint->verb->idempotencyKey) {
            $key = self::uuid();
            $extraHeaders[$definition->idempotencyHeader] = $definition->quoteIdempotencyKey ? '"'.$key.'"' : $key;
        }

        $build = function () use ($endpoint, $connection, $arguments, $extraQuery, $url, $options, &$extraHeaders): array {
            $credentials = $this->authenticator->credentials($connection, $options);
            $request = $this->builder->build($endpoint, $connection, $arguments, $credentials, $extraQuery, $url);
            if ($endpoint->verb->ifMatch) {
                $etag = $this->store->get(EtagMemory::key($connection->name, $request->url))['etag'] ?? null;
                if (is_string($etag)) {
                    $extraHeaders['If-Match'] = $etag;
                }
            }

            return [$request->withHeaders($extraHeaders), $credentials->identity];
        };
        [$request, $identity] = $build();

        return new Execution($endpoint, $connection, $arguments, $request, $identity, $options, $build(...));
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
