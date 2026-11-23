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
use KonradMichalik\Typo3Outbound\Enum\BodyFormat;

/**
 * Body.
 *
 * Sends the argument as request body, normalized and wrapped by the response
 * format of the connection.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Body
{
    public function __construct(
        public BodyFormat $format = BodyFormat::Json,
    ) {}
}
