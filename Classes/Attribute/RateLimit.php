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
 * RateLimit.
 *
 * Outgoing limit for the whole connection, shared by all its resources. In a web
 * request an exhausted limit fails immediately; in CLI the call waits.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public string $per = '1 minute',
    ) {}
}
