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
 * QueryStyle.
 *
 * How a parameter is written into the query string. Names follow the OpenAPI
 * serialization styles where one exists.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
enum QueryStyle: string
{
    /** Scalars as `name=value`, lists as repeated `name=a&name=b`. */
    case Form = 'form';

    /** Lists as `name=a,b`. */
    case CommaDelimited = 'comma';

    /** Lists as `name=a|b`. */
    case PipeDelimited = 'pipe';

    /** Lists as `name[]=a&name[]=b`. */
    case Brackets = 'brackets';

    /** Objects and maps as `name[key]=value`, nested as `name[a][b]=value`. */
    case DeepObject = 'deepObject';
}
