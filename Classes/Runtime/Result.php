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

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Result.
 *
 * The outcome of an execution before mapping.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Result
{
    /**
     * @param array<string, list<string>> $headers Lower-case header names
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public string $url,
        public int $retries = 0,
        public bool $fromCache = false,
        public ?ResponseInterface $response = null,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[$name][0] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
