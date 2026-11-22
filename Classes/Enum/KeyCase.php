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

namespace KonradMichalik\Typo3Outbound\Enum;

/**
 * KeyCase.
 *
 * How the keys of a response are converted before mapping onto a DTO.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
enum KeyCase: string
{
    /** Keys are taken as they are. */
    case None = 'none';

    /** snake_case and kebab-case keys are converted to camelCase. */
    case Camel = 'camel';
}
