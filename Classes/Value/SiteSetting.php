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

use function sprintf;

/**
 * SiteSetting.
 *
 * A setting of the current TYPO3 site. Never use it for secrets; see Secret.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class SiteSetting implements ValueSource
{
    public function __construct(
        public string $key,
    ) {}

    public function describe(): string
    {
        return sprintf('site(%s)', $this->key);
    }
}
