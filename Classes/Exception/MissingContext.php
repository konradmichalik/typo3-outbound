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
 * MissingContext.
 *
 * A value needs a context that is not available, typically a site setting in CLI
 * or a Messenger handler without an explicitly selected site.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MissingContext extends LogicException {}
