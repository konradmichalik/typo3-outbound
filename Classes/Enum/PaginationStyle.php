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
 * PaginationStyle.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
enum PaginationStyle: string
{
    /** Page number and page size as query parameters. */
    case Page = 'page';

    /** Offset and limit as query parameters. */
    case Offset = 'offset';

    /** A cursor read from the response body and sent back as query parameter. */
    case Cursor = 'cursor';

    /** `rel="next"` from the `Link` header (RFC 8288). */
    case LinkHeader = 'link';

    /** The next-page link the response format finds in the document (JSON:API, Hydra). */
    case Format = 'format';
}
