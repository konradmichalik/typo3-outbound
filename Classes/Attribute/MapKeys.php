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

use Attribute;

/**
 * MapKeys.
 *
 * Maps differing or nested response keys onto DTO properties, e.g.
 * ['towns' => 'cities', 'towns.*.label' => 'name'].
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class MapKeys
{
    /**
     * @param array<string, string> $map
     */
    public function __construct(
        public array $map,
    ) {}
}
