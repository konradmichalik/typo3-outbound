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

namespace KonradMichalik\Typo3Outbound\Value;

/**
 * ValueSource.
 *
 * A value that is resolved at call time instead of being written into the
 * attribute: an environment variable, a site setting or a secret.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ValueSource
{
    /**
     * A human-readable description for debug output, never the value itself.
     */
    public function describe(): string;
}
