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
use function is_array;
use function is_string;

/**
 * PlainJson.
 *
 * Default profile. Documents are used as they are; errors are read from
 * application/problem+json (RFC 9457), including the common "invalid-params"
 * and "errors" extensions.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PlainJson implements ResponseFormat
{
    public function accept(): string
    {
        return 'application/json, application/problem+json;q=0.9';
    }

    public function contentType(): string
    {
        return 'application/json';
    }

    public function encodeBody(array $payload): array
    {
        return $payload;
    }

    public function item(mixed $document): mixed
    {
        return $document;
    }

    public function collection(mixed $document): array
    {
        return is_array($document) && array_is_list($document) ? $document : [];
    }

    public function nextLink(mixed $document): ?string
    {
        return null;
    }

    public function error(int $status, mixed $document): ?ErrorDocument
    {
        if (!is_array($document)) {
            return null;
        }

        $fieldErrors = [];
        foreach ((array) ($document['invalid-params'] ?? []) as $param) {
            if (is_array($param) && is_string($param['name'] ?? null)) {
                $fieldErrors[$param['name']][] = is_string($param['reason'] ?? null) ? $param['reason'] : 'invalid';
            }
        }
        foreach ((array) ($document['errors'] ?? []) as $field => $messages) {
            if (!is_string($field)) {
                continue;
            }
            foreach ((array) $messages as $message) {
                if (is_string($message)) {
                    $fieldErrors[$field][] = $message;
                }
            }
        }

        $title = is_string($document['title'] ?? null) ? $document['title'] : null;
        $detail = is_string($document['detail'] ?? null) ? $document['detail'] : null;

        return null === $title && null === $detail && [] === $fieldErrors ? null : new ErrorDocument($title, $detail, $fieldErrors);
    }
}
