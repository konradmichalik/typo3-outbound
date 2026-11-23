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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Performance;

use CuyZ\Valinor\Cache\FileSystemCache;
use KonradMichalik\OutboundPetstore\Api\{PetApi, PetstoreApi, StoreApi};
use KonradMichalik\OutboundPetstore\Dto\PetDto;
use KonradMichalik\Typo3Outbound\Attribute\Connection;
use KonradMichalik\Typo3Outbound\Mapping\ValinorMapper;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{CountingStateStore, FakeClock, PetstoreKernel};
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

/**
 * BackendAccessTest.
 *
 * How often one API call touches the shared state store. In TYPO3 every access
 * is a cache backend query, so the normal path should need as few as possible.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class BackendAccessTest extends TestCase
{
    #[Test]
    public function successfulCallsReadTheBreakerOnceAndTheQuotaOncePerProcess(): void
    {
        $store = new CountingStateStore();
        $mock = new MockTransport();
        $mock->on(StoreApi::class, 'inventory')->respondWithJson(['available' => 1], 200, ['etag' => '"v1"']);
        $api = PetstoreKernel::create($mock, new FakeClock())->withStateStore($store)->api(StoreApi::class);

        $api->inventory();
        $api->inventory();
        $api->inventory();

        self::assertSame(3, $store->count('get', 'breaker_'), 'one breaker read per call');
        self::assertSame(1, $store->count('get', 'quota_'), 'the announced quota is read once, not per call');
        self::assertSame(0, $store->count('set', ''), 'no writes on the success path, not even for ETags');
        self::assertSame(0, $store->count('delete', ''));
        self::assertCount(4, $store->operations, 'three breaker reads and one quota read for three calls');
    }

    #[Test]
    public function breakerStateIsClearedAfterARecovery(): void
    {
        $store = new CountingStateStore();
        $mock = new MockTransport();
        $mock->on(PetApi::class, 'findByTags')->respondWith(503)->respondWith(503)->respondWith(503)->respondWithJson([]);
        $api = PetstoreKernel::create($mock, new FakeClock())->withStateStore($store)->api(PetApi::class);
        try {
            $api->findByTags(['a']);
        } catch (\KonradMichalik\Typo3Outbound\Exception\ApiUnavailable) {
        }
        self::assertSame(1, $store->count('set', 'breaker_'));

        $api->findByTags(['a']);
        $api->findByTags(['a']);

        self::assertSame(1, $store->count('delete', 'breaker_'), 'cleared once after the recovery, not on every success');
    }

    #[Test]
    public function mapperUsesAFileCacheWhenGivenOne(): void
    {
        $directory = sys_get_temp_dir().'/outbound-valinor-'.bin2hex(random_bytes(4));
        $mapper = new ValinorMapper(new FileSystemCache($directory));
        $connection = new ConnectionMetadata('petstore', PetstoreApi::class, new Connection('https://x'));

        $pet = $mapper->map(PetDto::class, ['name' => 'doggie', 'photoUrls' => []], $connection, null, 'PetApi::find');

        self::assertInstanceOf(PetDto::class, $pet);
        self::assertNotEmpty(glob($directory.'/*'));
    }
}
