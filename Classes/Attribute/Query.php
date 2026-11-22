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

use KonradMichalik\Typo3Outbound\Enum\QueryStyle;

/**
 * Query.
 *
 * Sends the argument as query parameter. Objects are normalized and written
 * according to the style; null values and empty lists are omitted.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Query
{
    public function __construct(
        public ?string $name = null,
        public QueryStyle $style = QueryStyle::Form,
    ) {}
}
