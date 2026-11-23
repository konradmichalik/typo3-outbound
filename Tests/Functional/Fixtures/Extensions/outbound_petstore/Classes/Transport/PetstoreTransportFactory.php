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

use KonradMichalik\Outbound\Transport\{SymfonyTransport, Transport};

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PetstoreTransportFactory
{
    public static function create(): Transport
    {
        // The header switches a single request to the fixture responses, so `ddev benchmark` measures without network latency.
        $mock = '1' === getenv('OUTBOUND_PETSTORE_MOCK') || '1' === ($_SERVER['HTTP_X_OUTBOUND_PETSTORE_MOCK'] ?? null);

        return $mock ? MockPetstore::create() : SymfonyTransport::create();
    }
}
