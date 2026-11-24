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

use KonradMichalik\Outbound\Cache\CacheEntry;
use KonradMichalik\Typo3Outbound\Cache\CacheResponseCache;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

/**
 * CacheResponseCacheTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CacheResponseCache::class)]
final class CacheResponseCacheTest extends TestCase
{
    private VariableFrontend $frontend;
    private CacheResponseCache $cache;

    protected function setUp(): void
    {
        $this->frontend = new VariableFrontend('outbound', new TransientMemoryBackend([]));
        $this->cache = new CacheResponseCache($this->frontend);
    }

    #[Test]
    public function anEntryComesBackUnchanged(): void
    {
        $entry = new CacheEntry(200, '{"id":1}', ['content-type' => ['application/json']], '"abc"', 1_800_000_060, 1_800_000_120);

        $this->cache->set('pet_1', $entry, 60, ['outbound_petstore']);

        self::assertEquals($entry, $this->cache->get('pet_1'));
    }

    #[Test]
    public function anUnknownKeyIsAMiss(): void
    {
        self::assertNull($this->cache->get('missing'));
    }

    /**
     * @param array<string, mixed> $stored
     */
    #[Test]
    #[DataProvider('brokenPayloads')]
    public function aBrokenPayloadIsAMissInsteadOfAnError(array $stored): void
    {
        $this->frontend->set('broken', $stored);

        self::assertNull($this->cache->get('broken'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function brokenPayloads(): iterable
    {
        yield 'status is not an integer' => [['status' => '200', 'body' => '', 'headers' => [], 'freshUntil' => 1, 'staleUntil' => 2]];
        yield 'body is missing' => [['status' => 200, 'headers' => [], 'freshUntil' => 1, 'staleUntil' => 2]];
        yield 'empty' => [[]];
    }

    #[Test]
    public function flushingATagRemovesOnlyEntriesCarryingIt(): void
    {
        $entry = new CacheEntry(200, '{}', [], null, 1_800_000_060, 1_800_000_120);
        $this->cache->set('tagged', $entry, 60, ['outbound_petstore']);
        $this->cache->set('other', $entry, 60, ['outbound_store']);

        $this->cache->flushTags(['outbound_petstore']);

        self::assertNull($this->cache->get('tagged'));
        self::assertNotNull($this->cache->get('other'));
    }
}
