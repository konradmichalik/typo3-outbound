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

namespace KonradMichalik\Typo3Outbound\Tests\Unit;

use KonradMichalik\Typo3Outbound\Resilience\CacheStateStore;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\{FrontendInterface, VariableFrontend};

use function hash;

/**
 * CacheStateStoreTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CacheStateStore::class)]
final class CacheStateStoreTest extends TestCase
{
    #[Test]
    public function aValueCanBeStoredReadAndDeleted(): void
    {
        $store = new CacheStateStore(new VariableFrontend('outbound_state', new TransientMemoryBackend([])));

        $store->set('breaker:PetApi', ['state' => 'open', 'failures' => 5], 30);
        self::assertSame(['state' => 'open', 'failures' => 5], $store->get('breaker:PetApi'));

        $store->delete('breaker:PetApi');
        self::assertNull($store->get('breaker:PetApi'));
    }

    #[Test]
    public function keysWithCharactersThatAreInvalidCacheIdentifiersWork(): void
    {
        $store = new CacheStateStore(new VariableFrontend('outbound_state', new TransientMemoryBackend([])));

        $store->set('KonradMichalik\\Api::call/1', ['n' => 1], 30);

        self::assertSame(['n' => 1], $store->get('KonradMichalik\\Api::call/1'));
    }

    #[Test]
    public function theTtlIsPassedToTheCacheAsLifetime(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('set')->with(hash('xxh128', 'k'), ['a' => 1], [], 45);

        (new CacheStateStore($cache))->set('k', ['a' => 1], 45);
    }

    #[Test]
    public function aNonArrayValueInTheCacheIsAMiss(): void
    {
        $frontend = new VariableFrontend('outbound_state', new TransientMemoryBackend([]));
        $frontend->set(hash('xxh128', 'k'), 'not-an-array');

        self::assertNull((new CacheStateStore($frontend))->get('k'));
    }
}
