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

namespace KonradMichalik\Typo3Outbound\Testing;

use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * SwitchableTransport.
 *
 * Decorates the configured transport. OutboundTestTrait installs a mock here
 * statically, because a TYPO3 frontend subrequest in a functional test boots a
 * fresh container that would never see a service replaced on the test's one.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class SwitchableTransport implements Transport
{
    private static ?Transport $override = null;

    public function __construct(private readonly Transport $inner) {}

    public static function override(?Transport $transport): void
    {
        self::$override = $transport;
    }

    public function request(Call $call): ResponseInterface
    {
        return (self::$override ?? $this->inner)->request($call);
    }
}
