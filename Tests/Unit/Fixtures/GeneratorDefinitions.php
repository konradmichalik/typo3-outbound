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

use Generator;
use KonradMichalik\Typo3Outbound\Attribute as Outbound;

require_once __DIR__.'/Definitions.php';

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://generators.example.com')]
interface GeneratorBaseApi {}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource('/g')]
interface GeneratorApi extends GeneratorBaseApi
{
    /** @return Generator<int, ItemDto, mixed, void> */
    #[Outbound\Get('/four')]
    #[Outbound\Paginate]
    public function four(): Generator;

    /** @return Generator<ItemDto> */
    #[Outbound\Get('/one')]
    #[Outbound\Paginate]
    public function one(): Generator;

    /** @return iterable<int, ItemDto> */
    #[Outbound\Get('/keyed')]
    #[Outbound\Paginate]
    public function keyed(): iterable;
}
