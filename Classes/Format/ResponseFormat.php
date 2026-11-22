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

/**
 * ResponseFormat.
 *
 * Encapsulates the conventions of an API format: envelopes, normalization of
 * resource objects, next-page links and error documents. Interfaces and DTOs stay
 * free of format details; switching the profile changes no endpoint.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ResponseFormat
{
    /**
     * Value of the Accept header sent with every request.
     */
    public function accept(): string;

    /**
     * Content type of request bodies encoded by encodeBody().
     */
    public function contentType(): string;

    /**
     * Wraps a normalized request payload into the format's envelope.
     *
     * @param array<array-key, mixed> $payload
     *
     * @return array<array-key, mixed>
     */
    public function encodeBody(array $payload): array;

    /**
     * Extracts and normalizes a single resource from a decoded document.
     */
    public function item(mixed $document): mixed;

    /**
     * Extracts and normalizes the members of a collection document.
     *
     * @return list<mixed>
     */
    public function collection(mixed $document): array;

    /**
     * The link to the next page found in the document, if any.
     */
    public function nextLink(mixed $document): ?string;

    /**
     * Interprets an error document; null when the body carries no usable error.
     */
    public function error(int $status, mixed $document): ?ErrorDocument;
}
