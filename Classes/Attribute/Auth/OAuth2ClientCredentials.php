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

use KonradMichalik\Typo3Outbound\Value\ValueSource;

/**
 * OAuth2ClientCredentials.
 *
 * Fetches an access token with the client credentials grant and shares it across
 * all resources of the connection until shortly before it expires.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class OAuth2ClientCredentials implements Authentication
{
    public function __construct(
        public string|ValueSource $tokenUrl,
        public string|ValueSource $clientId,
        public string|ValueSource $clientSecret,
        public ?string $scope = null,
    ) {}
}
