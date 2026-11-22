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
 * Secret.
 *
 * A site setting that holds a reference of the form `env:NAME` instead of the
 * secret itself. The secret is read from the environment variable, so it never
 * lands in a site's settings.yaml, in Git or in TypoScript.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Secret implements ValueSource
{
    public function __construct(
        public string $key,
    ) {}

    public function describe(): string
    {
        return sprintf('secret(%s)', $this->key);
    }
}
