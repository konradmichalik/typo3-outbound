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
use KonradMichalik\Typo3Outbound\Value\Deferred;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
enum Shelf: string
{
    case Top = 'top';
    case Bottom = 'bottom';
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://shop.example.com')]
interface ShopApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource('/products')]
interface ProductsApi extends ShopApi
{
    #[Outbound\Get('/{id}')]
    #[Outbound\Cache(lifetime: 60, tags: ['product_{id}'], staleOnError: 600)]
    public function find(int $id): ItemDto;

    /** @return Deferred<ItemDto> */
    #[Outbound\Get('/{id}')]
    #[Outbound\Cache(lifetime: 60, tags: ['product_{id}'])]
    public function later(int $id): Deferred;

    /** @return list<ItemDto> */
    #[Outbound\Get('/shelf/{shelf}')]
    #[Outbound\Cache(lifetime: 60, tags: ['products', 'shelf_{shelf}'])]
    public function onShelf(Shelf $shelf): array;

    #[Outbound\Put('/{id}')]
    #[Outbound\InvalidatesCache(tags: ['product_{id}', 'products'])]
    public function update(int $id, #[Outbound\Body] ItemDto $item): ItemDto;

    #[Outbound\Delete('/{id}')]
    #[Outbound\InvalidatesCache(tags: ['never_cached_{id}'])]
    public function remove(int $id): void;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://bad.example.com')]
interface BadTagsApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface DotInTagApi extends BadTagsApi
{
    #[Outbound\Get('/a/{id}')]
    #[Outbound\Cache(lifetime: 10, tags: ['item.{id}'])]
    public function a(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface UnknownPlaceholderApi extends BadTagsApi
{
    #[Outbound\Get('/a/{id}')]
    #[Outbound\Cache(lifetime: 10, tags: ['item_{uid}'])]
    public function a(int $id): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface NullablePlaceholderApi extends BadTagsApi
{
    #[Outbound\Get('/a')]
    #[Outbound\Cache(lifetime: 10, tags: ['item_{id}'])]
    public function a(#[Outbound\Query] ?int $id = null): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource]
interface FloatPlaceholderApi extends BadTagsApi
{
    #[Outbound\Get('/a')]
    #[Outbound\InvalidatesCache(tags: ['price_{price}'])]
    public function a(#[Outbound\Query] float $price): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource('/search')]
interface SearchApi extends ShopApi
{
    /**
     * @param array<string, mixed> $filter
     */
    #[Outbound\Post('/', idempotent: true)]
    #[Outbound\Cache(lifetime: 60)]
    public function search(#[Outbound\Body] array $filter): ItemDto;

    #[Outbound\Get('/tenant')]
    #[Outbound\Cache(lifetime: 60)]
    public function forTenant(#[Outbound\Header('X-Tenant')] string $tenant): ItemDto;
}
