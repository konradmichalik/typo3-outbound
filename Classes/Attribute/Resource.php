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

/**
 * Resource.
 *
 * Marks a resource interface. It extends exactly one base interface carrying
 * #[Connection] and contributes its path prefix.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Resource
{
    public function __construct(
        public string $prefix = '',
    ) {}
}
