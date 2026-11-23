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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Cache;

use Closure;
use KonradMichalik\Typo3Outbound\Cache\{CacheEntry, CacheKey, CacheLock, CacheLockHandle, CacheTags, InMemoryResponseCache, ResponseCache};
use KonradMichalik\Typo3Outbound\Command\{EndpointLocator, LintCommand};
use KonradMichalik\Typo3Outbound\Exception\{ApiTransportFailed, InvalidDefinition};
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, RecordingTagReporter};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{DotInTagApi, FloatPlaceholderApi, ItemDto, NullablePlaceholderApi, ProductsApi, SearchApi, Shelf, UnknownPlaceholderApi};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../Fixtures/Definitions.php';
require_once __DIR__.'/../Fixtures/CacheDefinitions.php';

/**
 * CacheBehaviourTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(\KonradMichalik\Typo3Outbound\Runtime\CacheLayer::class)]
#[CoversClass(CacheTags::class)]
final class CacheBehaviourTest extends TestCase
{
    private MockTransport $mock;
    private FakeClock $clock;
    private RecordingTagReporter $reporter;
    private CountingCache $cache;

    protected function setUp(): void
    {
        $this->mock = new MockTransport();
        $this->clock = new FakeClock();
        $this->reporter = new RecordingTagReporter();
        $this->cache = new CountingCache(new InMemoryResponseCache());
    }

    #[Test]
    public function aHitReportsItsRemainingLifetime(): void
    {
        $this->mock->on(ProductsApi::class, 'find')->respondWithJson(['id' => 1]);
        $api = $this->api();
        $api->find(1);

        $this->clock->advance(45);
        $api->find(1);

        self::assertSame(60, $this->reporter->reports[0]['lifetime']);
        self::assertSame(15, $this->reporter->reports[1]['lifetime']);
    }

    #[Test]
    public function aMissReadsTheCacheExactlyOnce(): void
    {
        $this->mock->on(ProductsApi::class, 'find')->respondWithJson(['id' => 1]);

        $this->api()->find(1);

        self::assertSame(1, $this->cache->reads);
    }

    #[Test]
    public function parallelMissesForTheSameKeySendOneRequestWithoutWaiting(): void
    {
        $this->mock->on(ProductsApi::class, 'later')->respondWithJson(['id' => 1]);
        $api = $this->api();

        $first = $api->later(1);
        $second = $api->later(1);
        $other = $api->later(2);

        self::assertSame(2, $this->mock->count(ProductsApi::class, 'later'));
        self::assertSame(1, $second->get()->id);
        self::assertSame(1, $first->get()->id);
        self::assertSame(1, $other->get()->id);
        self::assertSame([], $this->clock->sleeps);
    }

    #[Test]
    public function revalidationRenewsTheEntryReadInBegin(): void
    {
        $this->mock->on(ProductsApi::class, 'later')->respondWithJson(['id' => 1, 'name' => 'v1'], 200, ['etag' => '"v1"'])->respondWith(304);
        $api = $this->api();
        $api->later(1)->get();
        $this->clock->advance(61);

        $deferred = $api->later(1);
        $key = $this->onlyKey();
        $this->cache->set($key, new CacheEntry(200, '{"id":1,"name":"v2"}', [], '"v2"', 0, 0), 60, []);

        self::assertSame('v1', $deferred->get()->name);
        self::assertSame('"v1"', $this->mock->calls(ProductsApi::class, 'later')[1]->request->headers['If-None-Match']);
    }

    #[Test]
    public function writeEndpointFlushesTagsAfterSuccessOnly(): void
    {
        $this->mock->on(ProductsApi::class, 'find')->respondWithJson(['id' => 1]);
        $this->mock->on(ProductsApi::class, 'update')->respondWith(400)->respondWithJson(['id' => 1]);
        $api = $this->api();
        $api->find(1);

        try {
            $api->update(1, new ItemDto(1));
        } catch (\KonradMichalik\Typo3Outbound\Exception\ApiValidationFailed) {
        }
        $api->find(1);
        self::assertSame(1, $this->mock->count(ProductsApi::class, 'find'));

        $api->update(1, new ItemDto(1));
        $api->find(1);
        self::assertSame(2, $this->mock->count(ProductsApi::class, 'find'));
    }

    #[Test]
    public function lockIsReleasedWhenTheCallFails(): void
    {
        $lock = new RecordingLock();
        $this->mock->on(ProductsApi::class, 'find')->failWith('Connection refused');

        try {
            $this->api($lock)->find(1);
            self::fail('Expected a transport failure.');
        } catch (ApiTransportFailed) {
        }

        self::assertSame(1, $lock->acquired);
        self::assertSame(1, $lock->released);
    }

    #[Test]
    public function lockOfANeverResolvedDeferredIsReleasedOnGarbageCollection(): void
    {
        $lock = new RecordingLock();
        $this->mock->on(ProductsApi::class, 'later')->respondWithJson(['id' => 1]);
        $api = $this->api($lock);

        $deferred = $api->later(1);
        self::assertSame(0, $lock->released);
        unset($deferred);
        gc_collect_cycles();

        self::assertSame(1, $lock->released);
    }

    #[Test]
    public function afterWaitingForTheLockTheFreshEntryOfTheOtherProcessIsUsed(): void
    {
        $cache = $this->cache;
        $lock = new RecordingLock(static function (string $key) use ($cache): void {
            $cache->set($key, new CacheEntry(200, '{"id":1,"name":"from other process"}', [], null, 1_800_000_060, 1_800_000_060), 60, []);
        });

        self::assertSame('from other process', $this->api($lock)->find(1)->name);
        self::assertSame(0, $this->mock->count(ProductsApi::class, 'find'));
    }

    #[Test]
    public function aStaleFallbackDoesNotLetThePageCacheKeepTheOldDataForTheWholeStaleWindow(): void
    {
        $this->mock->on(ProductsApi::class, 'find')->respondWithJson(['id' => 1, 'name' => 'old'])->respondWith(500);
        $api = $this->api();
        $api->find(1);
        $this->clock->advance(120);

        self::assertSame('old', $api->find(1)->name);

        self::assertSame(60, $this->reporter->reports[0]['lifetime']);
        self::assertLessThanOrEqual(10, $this->reporter->reports[1]['lifetime']);
    }

    #[Test]
    public function busyLockServesAUsableStaleEntryInsteadOfWaiting(): void
    {
        $this->mock->on(ProductsApi::class, 'find')->respondWithJson(['id' => 1, 'name' => 'old']);
        $lock = new RecordingLock();
        $api = $this->api($lock);
        $api->find(1);
        $this->clock->advance(61);
        $lock->busy = true;

        self::assertSame('old', $api->find(1)->name);
        self::assertSame(1, $this->mock->count(ProductsApi::class, 'find'));
        self::assertSame(0.0, end($lock->timeouts));
    }

    /**
     * @param class-string $interface
     */
    #[Test]
    #[DataProvider('badTemplates')]
    public function badTagTemplatesFailTheBuild(string $interface, int $code): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionCode($code);

        Registry::build([$interface]);
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function badTemplates(): iterable
    {
        yield 'dot in the static part' => [DotInTagApi::class, 1794700031];
        yield 'unknown placeholder' => [UnknownPlaceholderApi::class, 1794700032];
        yield 'nullable parameter' => [NullablePlaceholderApi::class, 1794700033];
        yield 'float parameter' => [FloatPlaceholderApi::class, 1794700033];
    }

    #[Test]
    public function tagValuesNeverCollide(): void
    {
        self::assertSame('t_a_b', CacheTags::tag('t_{v}', ['v' => 'a_b']));
        self::assertNotSame(CacheTags::tag('t_{v}', ['v' => 'a.b']), CacheTags::tag('t_{v}', ['v' => 'a_b']));
        self::assertMatchesRegularExpression('/^t_h[0-9a-f]{16}$/', CacheTags::tag('t_{v}', ['v' => 'a.b']));
        $hashedLooking = 'h'.substr(hash('xxh128', 'a.b'), 0, 16);
        self::assertNotSame(CacheTags::tag('t_{v}', ['v' => 'a.b']), CacheTags::tag('t_{v}', ['v' => $hashedLooking]));
        self::assertSame('shelf_top', CacheTags::tag('shelf_{s}', ['s' => Shelf::Top]));
        self::assertSame('product_42', CacheTags::tag('product_{id}', ['id' => 42]));

        $this->expectExceptionCode(1794700035);
        CacheTags::tag('t_{v}', []);
    }

    #[Test]
    public function lintWarnsAboutInvalidationTagsNoCacheSets(): void
    {
        $registry = Registry::build([ProductsApi::class]);
        $tester = new CommandTester(new LintCommand($registry, new EndpointLocator($registry)));
        $tester->execute([]);

        self::assertStringContainsString('ProductsApi::remove: #[InvalidatesCache] tag "never_cached_{id}" is not set by any #[Cache]', $tester->getDisplay());
        self::assertStringNotContainsString('"products" is not set', $tester->getDisplay());
    }

    #[Test]
    public function differentBodiesNeverShareAnEntry(): void
    {
        $this->mock->on(SearchApi::class, 'search')->respondWithJson(['id' => 1]);
        $api = $this->searchApi();

        $api->search(['a' => 1]);
        $api->search(['a' => 2]);
        $api->search(['a' => 1]);

        self::assertSame(2, $this->mock->count(SearchApi::class, 'search'));
    }

    #[Test]
    public function differentHeaderParametersNeverShareAnEntry(): void
    {
        $this->mock->on(SearchApi::class, 'forTenant')->respondWithJson(['id' => 1]);
        $api = $this->searchApi();

        $api->forTenant('a');
        $api->forTenant('b');
        $api->forTenant('a');

        self::assertSame(2, $this->mock->count(SearchApi::class, 'forTenant'));
    }

    private function searchApi(): SearchApi
    {
        return Outbound::create([SearchApi::class])
            ->withTransport($this->mock)
            ->withClock($this->clock)
            ->withCache($this->cache)
            ->withCacheTagReporter($this->reporter)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(SearchApi::class);
    }

    private function api(?CacheLock $lock = null): ProductsApi
    {
        $outbound = Outbound::create([ProductsApi::class])
            ->withTransport($this->mock)
            ->withClock($this->clock)
            ->withCache($this->cache)
            ->withCacheTagReporter($this->reporter)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'));

        return (null !== $lock ? $outbound->withCacheLock($lock) : $outbound)->api(ProductsApi::class);
    }

    private function onlyKey(): string
    {
        return CacheKey::for('ProductsApi::later', 'https://shop.example.com/products/1', 'anonymous:none', null);
    }
}

/**
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CountingCache implements ResponseCache
{
    public int $reads = 0;

    public function __construct(private readonly ResponseCache $inner) {}

    public function get(string $key): ?CacheEntry
    {
        ++$this->reads;

        return $this->inner->get($key);
    }

    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void
    {
        $this->inner->set($key, $entry, $lifetime, $tags);
    }

    public function flushTags(array $tags): void
    {
        $this->inner->flushTags($tags);
    }
}

/**
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RecordingLock implements CacheLock
{
    public int $acquired = 0;
    public int $released = 0;
    public bool $busy = false;

    /** @var list<float> */
    public array $timeouts = [];

    /**
     * @param (Closure(string): void)|null $otherProcess Runs while "waiting", as if another process held the lock
     */
    public function __construct(private readonly ?Closure $otherProcess = null) {}

    public function acquire(string $key, float $timeout): ?CacheLockHandle
    {
        $this->timeouts[] = $timeout;
        if ($this->busy) {
            return null;
        }
        if (null !== $this->otherProcess) {
            ($this->otherProcess)($key);
        }
        ++$this->acquired;

        return new CacheLockHandle(function (): void {
            ++$this->released;
        }, null !== $this->otherProcess);
    }
}
