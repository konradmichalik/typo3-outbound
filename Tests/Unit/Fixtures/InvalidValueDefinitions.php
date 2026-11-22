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
use KonradMichalik\Typo3Outbound\Enum\PaginationStyle;

require_once __DIR__.'/Definitions.php';

#[Outbound\Connection(baseUri: 'https://values.example.com')]
interface ValuesBaseApi {}

#[Outbound\Resource]
interface ZeroLifetimeApi extends ValuesBaseApi
{
    #[Outbound\Cache(lifetime: 0)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface NegativeLifetimeApi extends ValuesBaseApi
{
    #[Outbound\Cache(lifetime: -5)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface NegativeStaleApi extends ValuesBaseApi
{
    #[Outbound\Cache(lifetime: 60, staleOnError: -1)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface ZeroTimeoutApi extends ValuesBaseApi
{
    #[Outbound\Timeout(0)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface NegativeTimeoutApi extends ValuesBaseApi
{
    #[Outbound\Timeout(-1)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface NegativeRetriesApi extends ValuesBaseApi
{
    #[Outbound\Retry(maxRetries: -1)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface ShrinkingBackoffApi extends ValuesBaseApi
{
    #[Outbound\Retry(multiplier: 0.5)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface NegativeDelayApi extends ValuesBaseApi
{
    #[Outbound\Retry(delayMs: -10)]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface BadRetryStatusApi extends ValuesBaseApi
{
    #[Outbound\Retry(statusCodes: [42])]
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Resource]
interface ZeroPageSizeApi extends ValuesBaseApi
{
    /** @return iterable<ItemDto> */
    #[Outbound\Paginate(size: 0)]
    #[Outbound\Get('/a')]
    public function a(): iterable;
}

#[Outbound\Resource]
interface ZeroMaxPagesApi extends ValuesBaseApi
{
    /** @return iterable<ItemDto> */
    #[Outbound\Paginate(maxPages: 0)]
    #[Outbound\Get('/a')]
    public function a(): iterable;
}

#[Outbound\Resource]
interface CursorWithoutPathApi extends ValuesBaseApi
{
    /** @return iterable<ItemDto> */
    #[Outbound\Paginate(style: PaginationStyle::Cursor)]
    #[Outbound\Get('/a')]
    public function a(): iterable;
}

#[Outbound\Resource]
interface BracedPathApi extends ValuesBaseApi
{
    #[Outbound\Get('/a/{pet-id}')]
    public function a(): string;
}

#[Outbound\Connection(baseUri: 'https://zerothresholdbreaker.example.com')]
#[Outbound\CircuitBreaker(failureThreshold: 0)]
interface ZeroThresholdBreakerBase {}

#[Outbound\Resource]
interface ZeroThresholdBreakerApi extends ZeroThresholdBreakerBase
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Connection(baseUri: 'https://zerocooldownbreaker.example.com')]
#[Outbound\CircuitBreaker(cooldown: 0)]
interface ZeroCooldownBreakerBase {}

#[Outbound\Resource]
interface ZeroCooldownBreakerApi extends ZeroCooldownBreakerBase
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Connection(baseUri: 'https://garbageintervallimit.example.com')]
#[Outbound\RateLimit(10, per: 'garbage 5')]
interface GarbageIntervalLimitBase {}

#[Outbound\Resource]
interface GarbageIntervalLimitApi extends GarbageIntervalLimitBase
{
    #[Outbound\Get('/a')]
    public function a(): string;
}

#[Outbound\Connection(baseUri: 'https://zerointervallimit.example.com')]
#[Outbound\RateLimit(10, per: '0 seconds')]
interface ZeroIntervalLimitBase {}

#[Outbound\Resource]
interface ZeroIntervalLimitApi extends ZeroIntervalLimitBase
{
    #[Outbound\Get('/a')]
    public function a(): string;
}
