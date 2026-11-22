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

namespace KonradMichalik\Typo3Outbound\Attribute;

use KonradMichalik\Typo3Outbound\Enum\PaginationStyle;

/**
 * Paginate.
 *
 * The method returns an iterable that loads page after page lazily. `maxPages`
 * bounds the number of requests.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Paginate
{
    /**
     * @param string|null $itemsPath  Dot path of the items in the page document; null uses the format's collection
     * @param string|null $cursorPath Dot path of the next cursor for PaginationStyle::Cursor
     */
    public function __construct(
        public PaginationStyle $style = PaginationStyle::Page,
        public int $size = 50,
        public int $maxPages = 20,
        public string $pageParam = 'page',
        public string $sizeParam = 'size',
        public int $firstPage = 1,
        public ?string $itemsPath = null,
        public ?string $cursorPath = null,
        public string $cursorParam = 'cursor',
    ) {}
}
