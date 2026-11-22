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

namespace KonradMichalik\Typo3Outbound\Tests\Integration;

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi};
use KonradMichalik\OutboundPetstore\Dto\{PetDto, PetStatus};
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, PetstoreKernel};
use KonradMichalik\Typo3Outbound\Transport\SymfonyTransport;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

/**
 * PetstoreLiveTest.
 *
 * Calls the public Petstore. Opt-in with OUTBOUND_LIVE=1, because it needs
 * network access and the shared instance changes its data constantly.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class PetstoreLiveTest extends TestCase
{
    protected function setUp(): void
    {
        if ('1' !== getenv('OUTBOUND_LIVE')) {
            self::markTestSkipped('Set OUTBOUND_LIVE=1 to run tests against petstore3.swagger.io.');
        }
    }

    #[Test]
    public function availablePetsAreMapped(): void
    {
        $pets = PetstoreKernel::create(SymfonyTransport::create(), new FakeClock(), web: false)->api(PetApi::class)->findByStatus(PetStatus::Available);

        self::assertContainsOnlyInstancesOf(PetDto::class, $pets);
    }

    #[Test]
    public function addedPetCanBeReadBack(): void
    {
        $pets = PetstoreKernel::create(SymfonyTransport::create(), new FakeClock(), web: false)->api(PetApi::class);
        $added = $pets->add(new PetDto('typo3-outbound-'.bin2hex(random_bytes(3)), [], random_int(1_000_000, 9_000_000), status: PetStatus::Pending));

        self::assertSame($added->name, $pets->find((int) $added->id)?->name);
    }

    #[Test]
    public function inventoryIsAMapOfCounts(): void
    {
        $inventory = PetstoreKernel::create(SymfonyTransport::create(), new FakeClock(), web: false)->api(StoreApi::class)->inventory();

        self::assertContainsOnly('int', $inventory);
    }
}
