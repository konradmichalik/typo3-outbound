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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures;

use KonradMichalik\Typo3Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://limited.example.com')]
#[Outbound\RateLimit(1, per: '1 minute')]
#[Outbound\Retry(maxRetries: 3)]
interface LimitedApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://limited-oauth.example.com')]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: 'https://auth.limited-oauth.example.com/token', clientId: 'id', clientSecret: 'secret')]
#[Outbound\RateLimit(1, per: '1 minute')]
interface LimitedOAuthApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;
}
