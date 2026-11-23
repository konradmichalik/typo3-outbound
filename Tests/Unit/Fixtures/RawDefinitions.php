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
use KonradMichalik\Typo3Outbound\Value\RawResponse;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://files.example.com')]
interface DownloadsApi
{
    #[Outbound\Get('/files/{id}')]
    public function download(int $id): RawResponse;
}

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://small-files.example.com', maxResponseBytes: 100)]
interface SmallDownloadsApi
{
    #[Outbound\Get('/files/{id}')]
    public function download(int $id): RawResponse;
}
