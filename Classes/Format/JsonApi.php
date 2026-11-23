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

namespace KonradMichalik\Typo3Outbound\Format;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function end;
use function explode;
use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;
use function trim;

/**
 * JsonApi.
 *
 * JSON:API profile. Resource objects are flattened (`id`, `type` and the
 * attributes on one level) and relationships are replaced by the resources from
 * `included`, up to `includeDepth` levels, so cycles cannot recurse endlessly.
 * Unresolved relationships keep their identifier object `{id, type}`.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class JsonApi implements ResponseFormat
{
    public function __construct(
        public int $includeDepth = 3,
    ) {}

    public function accept(): string
    {
        return 'application/vnd.api+json';
    }

    public function contentType(): string
    {
        return 'application/vnd.api+json';
    }

    public function encodeBody(array $payload): array
    {
        $resource = [];
        foreach (['type', 'id'] as $member) {
            if (isset($payload[$member]) && is_scalar($payload[$member])) {
                $resource[$member] = (string) $payload[$member];
                unset($payload[$member]);
            }
        }
        $resource['attributes'] = $payload;

        return ['data' => $resource];
    }

    public function item(mixed $document): mixed
    {
        if (is_array($document) && array_key_exists('data', $document) && null === $document['data']) {
            return null;
        }
        if (!is_array($document) || !is_array($document['data'] ?? null)) {
            return $document;
        }

        return $this->flatten($document['data'], $this->index($document), $this->includeDepth);
    }

    public function collection(mixed $document): array
    {
        if (!is_array($document) || !is_array($document['data'] ?? null) || !array_is_list($document['data'])) {
            return [];
        }
        $included = $this->index($document);

        return array_map(
            fn (mixed $resource): mixed => is_array($resource) ? $this->flatten($resource, $included, $this->includeDepth) : $resource,
            $document['data'],
        );
    }

    public function nextLink(mixed $document): ?string
    {
        $next = is_array($document) && is_array($document['links'] ?? null) ? ($document['links']['next'] ?? null) : null;
        if (is_array($next)) {
            $next = $next['href'] ?? null;
        }

        return is_string($next) && '' !== $next ? $next : null;
    }

    public function error(int $status, mixed $document): ?ErrorDocument
    {
        if (!is_array($document) || !is_array($document['errors'] ?? null) || [] === $document['errors']) {
            return null;
        }

        $title = null;
        $fieldErrors = [];
        foreach ($document['errors'] as $error) {
            if (!is_array($error)) {
                continue;
            }
            $message = is_string($error['detail'] ?? null) ? $error['detail'] : (is_string($error['title'] ?? null) ? $error['title'] : 'invalid');
            $title ??= is_string($error['title'] ?? null) ? $error['title'] : null;
            $pointer = is_array($error['source'] ?? null) && is_string($error['source']['pointer'] ?? null) ? $error['source']['pointer'] : null;
            if (null !== $pointer) {
                $segments = explode('/', trim($pointer, '/'));
                $fieldErrors[end($segments)][] = $message;
            }
        }

        return new ErrorDocument($title, null, $fieldErrors);
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return array<string, array<array-key, mixed>>
     */
    private function index(array $document): array
    {
        $index = [];
        foreach ((array) ($document['included'] ?? []) as $resource) {
            if (is_array($resource) && is_scalar($resource['type'] ?? null) && is_scalar($resource['id'] ?? null)) {
                $index[sprintf('%s:%s', $resource['type'], $resource['id'])] = $resource;
            }
        }

        return $index;
    }

    /**
     * @param array<array-key, mixed>                $resource
     * @param array<string, array<array-key, mixed>> $included
     *
     * @return array<array-key, mixed>
     */
    private function flatten(array $resource, array $included, int $depth): array
    {
        $flat = [];
        if (isset($resource['id'])) {
            $flat['id'] = $resource['id'];
        }
        if (isset($resource['type'])) {
            $flat['type'] = $resource['type'];
        }
        foreach ((array) ($resource['attributes'] ?? []) as $name => $value) {
            $flat[$name] = $value;
        }
        foreach ((array) ($resource['relationships'] ?? []) as $name => $relationship) {
            if (!is_array($relationship) || !array_key_exists('data', $relationship)) {
                continue;
            }
            $data = $relationship['data'];
            $flat[$name] = match (true) {
                null === $data => null,
                is_array($data) && array_is_list($data) => array_map(fn (mixed $identifier): mixed => $this->resolve($identifier, $included, $depth), $data),
                default => $this->resolve($data, $included, $depth),
            };
        }

        return $flat;
    }

    /**
     * @param array<string, array<array-key, mixed>> $included
     */
    private function resolve(mixed $identifier, array $included, int $depth): mixed
    {
        if (!is_array($identifier) || !is_scalar($identifier['type'] ?? null) || !is_scalar($identifier['id'] ?? null)) {
            return $identifier;
        }
        $key = sprintf('%s:%s', $identifier['type'], $identifier['id']);
        if ($depth <= 0 || !isset($included[$key])) {
            return ['id' => $identifier['id'], 'type' => $identifier['type']];
        }

        return $this->flatten($included[$key], $included, $depth - 1);
    }
}
