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

namespace KonradMichalik\Typo3Outbound\Attribute\Auth;

use Attribute;
use KonradMichalik\Typo3Outbound\Value\ValueSource;

/**
 * TokenLogin.
 *
 * Posts the credentials as JSON to a login endpoint and sends the token from the
 * answer as bearer token, shared across all resources of the connection for ttl
 * seconds. A relative url is resolved against the base URI. Every body value
 * counts as sensitive.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class TokenLogin implements Authentication
{
    /**
     * @param array<array-key, string|ValueSource> $body
     */
    public function __construct(
        public string|ValueSource $url,
        public array $body,
        public string $tokenField = 'token',
        public int $ttl = 3600,
    ) {}
}
