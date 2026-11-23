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

namespace KonradMichalik\Typo3Outbound\Cache;

use function is_array;
use function is_int;
use function is_string;

/**
 * CacheEntry.
 *
 * A cached response body (raw JSON, never serialized objects) with its
 * freshness and stale windows.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class CacheEntry
{
    /**
     * @param array<string, list<string>> $headers Selected response headers, lower-case
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
        public ?string $etag,
        public int $freshUntil,
        public int $staleUntil,
    ) {}

    public function isFresh(int $now): bool
    {
        return $now < $this->freshUntil;
    }

    public function isUsableWhenStale(int $now): bool
    {
        return $now < $this->staleUntil;
    }

    public function renewed(int $now, int $lifetime, int $staleOnError): self
    {
        return new self($this->status, $this->body, $this->headers, $this->etag, $now + $lifetime, $now + $lifetime + $staleOnError);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'body' => $this->body, 'headers' => $this->headers, 'etag' => $this->etag, 'freshUntil' => $this->freshUntil, 'staleUntil' => $this->staleUntil];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!is_int($data['status'] ?? null) || !is_string($data['body'] ?? null) || !is_array($data['headers'] ?? null) || !is_int($data['freshUntil'] ?? null) || !is_int($data['staleUntil'] ?? null)) {
            return null;
        }
        $etag = $data['etag'] ?? null;

        /** @var array<string, list<string>> $headers */
        $headers = $data['headers'];

        return new self($data['status'], $data['body'], $headers, is_string($etag) ? $etag : null, $data['freshUntil'], $data['staleUntil']);
    }
}
