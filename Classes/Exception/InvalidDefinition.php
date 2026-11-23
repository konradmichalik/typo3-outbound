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

namespace KonradMichalik\Typo3Outbound\Exception;

use LogicException;

/**
 * InvalidDefinition.
 *
 * A connection, resource or endpoint declaration is invalid. Thrown while the
 * container is built, so a broken declaration never reaches production.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class InvalidDefinition extends LogicException {}
