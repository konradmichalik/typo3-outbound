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

final readonly class ItemDto
{
    public function __construct(public int $id, public string $name = '') {}
}

#[Outbound\Connection(baseUri: 'https://api.example.com/v1', allowedHosts: ['api.example.com', 'cdn.example.com'])]
#[Outbound\Retry(maxRetries: 1)]
#[Outbound\Timeout(4)]
interface MyCRMApi {}

#[Outbound\Resource('/items')]
#[Outbound\Timeout(2)]
interface ItemsApi extends MyCRMApi
{
    #[Outbound\Get('/{id}')]
    #[Outbound\Timeout(1)]
    public function find(int $id): ItemDto;

    /** @return iterable<ItemDto> */
    #[Outbound\Get]
    #[Outbound\Paginate(style: PaginationStyle::Page, size: 2, maxPages: 5)]
    public function pages(): iterable;

    /** @return iterable<ItemDto> */
    #[Outbound\Get('/cursor')]
    #[Outbound\Paginate(style: PaginationStyle::Cursor, size: 2, itemsPath: 'items', cursorPath: 'next')]
    public function cursor(): iterable;

    /** @return iterable<ItemDto> */
    #[Outbound\Get('/linked')]
    #[Outbound\Paginate(style: PaginationStyle::LinkHeader, size: 2)]
    public function linked(): iterable;
}

#[Outbound\Connection(baseUri: 'http://insecure.example.com')]
interface InsecureApi {}

#[Outbound\Resource]
interface OrphanApi {}

#[Outbound\Connection(baseUri: 'https://a.example.com')]
interface WithEndpointsApi
{
    #[Outbound\Get('/ping')]
    public function ping(): string;
}

#[Outbound\Resource('/x')]
interface ExtendsWithEndpointsApi extends WithEndpointsApi
{
    #[Outbound\Get]
    public function x(): string;
}

#[Outbound\Connection(baseUri: 'https://b.example.com')]
interface BrokenApi {}

#[Outbound\Resource]
interface NoRoleApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    public function a(int $id): string;
}

#[Outbound\Resource]
interface UnboundPlaceholderApi extends BrokenApi
{
    #[Outbound\Get('/a/{id}')]
    public function a(): string;
}

#[Outbound\Resource]
interface BodyOnGetApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    public function a(#[Outbound\Body] array $body): string;
}

#[Outbound\Resource]
interface CachedPostApi extends BrokenApi
{
    #[Outbound\Post('/a')]
    #[Outbound\Cache(lifetime: 10)]
    public function a(): string;
}

#[Outbound\Resource]
interface PaginateWithoutIterableApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    #[Outbound\Paginate]
    public function a(): array;
}
