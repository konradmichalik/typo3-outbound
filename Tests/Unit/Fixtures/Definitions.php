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

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ItemDto
{
    public function __construct(public int $id, public string $name = '') {}
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://api.example.com/v1', allowedHosts: ['api.example.com', 'cdn.example.com'])]
#[Outbound\Retry(maxRetries: 1)]
#[Outbound\Timeout(4)]
interface MyCRMApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
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

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'http://insecure.example.com')]
interface InsecureApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface OrphanApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://a.example.com')]
interface WithEndpointsApi
{
    #[Outbound\Get('/ping')]
    public function ping(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource('/x')]
interface ExtendsWithEndpointsApi extends WithEndpointsApi
{
    #[Outbound\Get]
    public function x(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://b.example.com')]
interface BrokenApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface NoRoleApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    public function a(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface UnboundPlaceholderApi extends BrokenApi
{
    #[Outbound\Get('/a/{id}')]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface BodyOnGetApi extends BrokenApi
{
    /**
     * @param array<string, mixed> $body
     */
    #[Outbound\Get('/a')]
    public function a(#[Outbound\Body] array $body): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface CachedPostApi extends BrokenApi
{
    #[Outbound\Post('/a')]
    #[Outbound\Cache(lifetime: 10)]
    public function a(): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface PaginateWithoutIterableApi extends BrokenApi
{
    /**
     * @return array<mixed>
     */
    #[Outbound\Get('/a')]
    #[Outbound\Paginate]
    public function a(): array;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://catalog.example.com')]
#[Outbound\Cache(lifetime: 60)]
interface CachedCatalogApi
{
    #[Outbound\Get('/items/{id}')]
    public function find(int $id): string;

    #[Outbound\Post('/items')]
    public function create(#[Outbound\Body] string $item): string;

    #[Outbound\Delete('/items/{id}')]
    public function remove(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface ByReferenceApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    public function a(int &$id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface NeverReturnApi extends BrokenApi
{
    #[Outbound\Get('/a')]
    public function a(): never;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface DuplicatePathApi extends BrokenApi
{
    #[Outbound\Get('/a/{id}')]
    public function a(int $id, #[Outbound\Path('id')] int $other): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface NullablePathApi extends BrokenApi
{
    #[Outbound\Get('/a/{id}')]
    public function a(?int $id = null): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://untyped.example.com')]
interface UntypedPathApi
{
    #[Outbound\Get('/a/{id}')]
    public function a($id): string;
}
