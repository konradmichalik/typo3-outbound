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

namespace KonradMichalik\OutboundPetstore\Transport;

use KonradMichalik\Typo3Outbound\Transport\{SymfonyTransport, Transport};

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PetstoreTransportFactory
{
    public static function create(): Transport
    {
        return '1' === getenv('OUTBOUND_PETSTORE_MOCK') ? MockPetstore::create() : SymfonyTransport::create();
    }
}
