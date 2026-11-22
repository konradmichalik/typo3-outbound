<?php

declare(strict_types=1);

namespace KonradMichalik\OutboundPetstore\Transport;

use KonradMichalik\Typo3Outbound\Transport\{SymfonyTransport, Transport};

final class PetstoreTransportFactory
{
    public static function create(): Transport
    {
        return '1' === getenv('OUTBOUND_PETSTORE_MOCK') ? MockPetstore::create() : SymfonyTransport::create();
    }
}
