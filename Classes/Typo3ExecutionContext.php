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

namespace KonradMichalik\Typo3Outbound;

use KonradMichalik\Outbound\Context\ExecutionContext;
use TYPO3\CMS\Core\Core\Environment;

/**
 * Typo3ExecutionContext.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Typo3ExecutionContext implements ExecutionContext
{
    public function isWeb(): bool
    {
        return !Environment::isCli();
    }

    public function isDevelopment(): bool
    {
        return Environment::getContext()->isDevelopment();
    }
}
