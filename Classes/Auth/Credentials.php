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

namespace KonradMichalik\Typo3Outbound\Auth;

/**
 * Credentials.
 *
 * Headers and query parameters that authenticate a request, plus a
 * non-reversible identity used to separate cache entries of different
 * principals.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Credentials
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     * @param list<string>          $sensitiveKeys
     */
    public function __construct(
        public array $headers = [],
        public array $query = [],
        public string $identity = 'anonymous',
        public array $sensitiveKeys = [],
    ) {}
}
