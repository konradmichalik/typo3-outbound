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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\Proxy;

use KonradMichalik\Typo3Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://constants.example.com')]
interface ConstantDefaultsApi
{
    public const MAX = 25;

    #[Outbound\Get('/items')]
    public function list(int $limit = self::MAX, int $cap = \PHP_INT_MAX, int $size = \PHP_INT_SIZE, int $other = ConstantHolder::LIMIT): string;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ConstantHolder
{
    public const LIMIT = 7;
}
