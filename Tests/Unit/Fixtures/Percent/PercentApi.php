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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\Percent;

use KonradMichalik\Typo3Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://percent.example.com')]
interface PercentApi
{
    #[Outbound\Get('/discount/100%%/%foo%')]
    public function discount(): void;
}
