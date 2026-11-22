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

namespace KonradMichalik\Typo3Outbound\Http;

/**
 * RequestSpec.
 *
 * A fully built request. `body` is a string for JSON and form bodies or an
 * iterable of chunks for multipart bodies.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RequestSpec
{
    /**
     * @param array<string, string>      $headers
     * @param string|iterable<string>|null $body
     * @param list<string>               $sensitiveKeys Query keys and header names to redact in logs
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public string|iterable|null $body = null,
        public array $sensitiveKeys = [],
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->method, $this->url, [...$this->headers, ...$headers], $this->body, $this->sensitiveKeys);
    }

    public function withUrl(string $url): self
    {
        return new self($this->method, $url, $this->headers, $this->body, $this->sensitiveKeys);
    }

    public function withoutHeader(string $name): self
    {
        $headers = $this->headers;
        unset($headers[$name]);

        return new self($this->method, $this->url, $headers, $this->body, $this->sensitiveKeys);
    }
}
