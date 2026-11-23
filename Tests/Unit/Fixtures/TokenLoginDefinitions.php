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
use KonradMichalik\Typo3Outbound\Value\{Env, SiteSetting};

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: '/auth/token', body: ['username' => 'user', 'password' => new Env('OUTBOUND_TEST_PASSWORD')], ttl: 600)]
interface TokenLoginApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: 'https://auth.example.com/login', body: ['username' => 'user', 'password' => new Env('OUTBOUND_TEST_PASSWORD')])]
interface AbsoluteTokenLoginApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: '/auth/token', body: [])]
interface EmptyBodyLoginApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: '/auth/token', body: ['user', 'secret'])]
interface ListBodyLoginApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: '/auth/token', body: ['username' => 'user'], tokenField: '')]
interface EmptyTokenFieldLoginApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: '/auth/token', body: ['username' => 'user'], ttl: 0)]
interface ZeroTtlLoginApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: 'http://login.example.com/auth/token', body: ['username' => 'user'])]
interface PlainHttpLoginApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://login.example.com')]
#[Outbound\Auth\TokenLogin(url: new SiteSetting('auth.loginUrl'), body: ['username' => 'user'])]
interface SiteLoginUrlWithoutHostsApi
{
    #[Outbound\Get('/a')]
    public function a(): string;
}
