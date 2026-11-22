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

use function array_key_exists;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * Hydra.
 *
 * Hydra/JSON-LD profile as served by API Platform. API Platform 4 omits the
 * "hydra:" prefix by default (`hydra_prefix`); `prefix` selects the expected
 * variant, the other one is still accepted when reading. `@id` becomes the key
 * `iri`, all other JSON-LD keywords are dropped.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Hydra implements ResponseFormat
{
    public function __construct(
        public bool $prefix = false,
    ) {}

    public function accept(): string
    {
        return 'application/ld+json';
    }

    public function contentType(): string
    {
        return 'application/ld+json';
    }

    public function encodeBody(array $payload): array
    {
        return $payload;
    }

    public function item(mixed $document): mixed
    {
        return $this->normalize($document);
    }

    public function collection(mixed $document): array
    {
        $members = $this->member($document, 'member');

        return is_array($members) ? array_values(array_map($this->normalize(...), $members)) : [];
    }

    public function nextLink(mixed $document): ?string
    {
        $view = $this->member($document, 'view');
        $next = is_array($view) ? $this->member($view, 'next') : null;

        return is_string($next) && '' !== $next ? $next : null;
    }

    public function error(int $status, mixed $document): ?ErrorDocument
    {
        if (!is_array($document)) {
            return null;
        }
        $title = $this->member($document, 'title');
        $detail = $this->member($document, 'description') ?? ($document['detail'] ?? null);

        $fieldErrors = [];
        foreach ((array) ($document['violations'] ?? []) as $violation) {
            if (is_array($violation) && is_string($violation['propertyPath'] ?? null) && is_string($violation['message'] ?? null)) {
                $fieldErrors[$violation['propertyPath']][] = $violation['message'];
            }
        }

        if (!is_string($title) && !is_string($detail) && [] === $fieldErrors) {
            return null;
        }

        return new ErrorDocument(is_string($title) ? $title : null, is_string($detail) ? $detail : null, $fieldErrors);
    }

    private function member(mixed $document, string $name): mixed
    {
        if (!is_array($document)) {
            return null;
        }
        [$preferred, $fallback] = $this->prefix ? ['hydra:'.$name, $name] : [$name, 'hydra:'.$name];

        return $document[$preferred] ?? $document[$fallback] ?? null;
    }

    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            if ('@id' === $key) {
                if (!array_key_exists('iri', $value)) {
                    $normalized['iri'] = $item;
                }
                continue;
            }
            if (is_string($key) && str_starts_with($key, '@')) {
                continue;
            }
            $normalized[$key] = $this->normalize($item);
        }

        return $normalized;
    }
}
