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
 * Multipart.
 *
 * Sends the argument as multipart/form-data body. The argument is a map of field
 * names to strings or FilePart instances.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Multipart {}
