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

namespace KonradMichalik\Typo3Outbound\Metadata;

/**
 * ReturnKind.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
enum ReturnKind: string
{
    /** The decoded response is mapped onto the signature. */
    case Mapped = 'mapped';

    /** Status is checked, the body is ignored. */
    case Void = 'void';

    /** The response is returned unmapped as RawResponse. */
    case Raw = 'raw';

    /** A paginated iterable of mapped items. */
    case Iterable = 'iterable';

    /** A Deferred wrapping the mapped result; the request starts immediately. */
    case Deferred = 'deferred';
}
