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
use KonradMichalik\Typo3Outbound\Enum\ApiKeyLocation;
use KonradMichalik\Typo3Outbound\Value\{Env, Setting};

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://keyed.example.com')]
#[Outbound\Auth\ApiKey(name: 'api_key', value: new Env('OUTBOUND_TEST_KEY'), in: ApiKeyLocation::Query)]
interface KeyedApi
{
    #[Outbound\Get('/files/{name}')]
    public function file(string $name): string;

    /**
     * @param array<string, string> $credentials
     */
    #[Outbound\Post('/login')]
    public function login(#[Outbound\Body] #[Outbound\Sensitive] array $credentials): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: new Setting('unsafe.baseUri'))]
interface SiteUriWithoutHostsApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface SiteUriWithoutHostsResource extends SiteUriWithoutHostsApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://api.example.com')]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: new Setting('auth.tokenUrl'), clientId: 'id', clientSecret: 'secret')]
interface SiteTokenUrlWithoutHostsApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: new Setting('tenant.baseUri'), allowedHosts: ['api.example.com'])]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: new Setting('tenant.tokenUrl'), clientId: 'id', clientSecret: 'secret')]
interface TenantApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://93.184.216.34', maxResponseBytes: 100)]
interface SmallLimitApi
{
    #[Outbound\Get('/blob')]
    public function blob(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://127.0.0.1')]
interface PrivateAddressApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'http://127.0.0.1', allowPrivateNetwork: true)]
interface IntranetApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://93.184.216.34', headers: ['X-Static' => new Env('OUTBOUND_TEST_KEY'), 'X-Plain' => 'visible'])]
interface StaticHeaderApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}
