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
 * Env.
 *
 * An environment variable, read via getenv() at call time.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Env implements ValueSource
{
    public function __construct(
        public string $name,
    ) {}

    public function describe(): string
    {
        return sprintf('env(%s)', $this->name);
    }
}
