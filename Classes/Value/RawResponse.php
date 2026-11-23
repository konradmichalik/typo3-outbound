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

namespace KonradMichalik\Typo3Outbound\Value;

use Closure;

use function strtolower;

/**
 * RawResponse.
 *
 * An unmapped response for downloads and streams. The body is only read when
 * content() or chunks() is called.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RawResponse
{
    /**
     * @param array<string, list<string>> $headers Lower-case header names
     * @param Closure(): string           $content
     * @param Closure(): iterable<string> $chunks
     */
    public function __construct(
        public int $status,
        public array $headers,
        private Closure $content,
        private Closure $chunks,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function content(): string
    {
        return ($this->content)();
    }

    /**
     * @return iterable<string>
     */
    public function chunks(): iterable
    {
        return ($this->chunks)();
    }
}
