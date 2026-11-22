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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Runtime;

use KonradMichalik\Typo3Outbound\Exception\BlockedRequest;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{ItemDto, ItemsApi};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';

/**
 * PaginatorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(\KonradMichalik\Typo3Outbound\Runtime\Paginator::class)]
final class PaginatorTest extends TestCase
{
    #[Test]
    public function pagesAreLoadedLazilyUntilAShortPage(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'pages')->respondWithJson([['id' => 1], ['id' => 2]])->respondWithJson([['id' => 3]]);
        $items = $this->api($mock)->pages();

        self::assertSame(0, $mock->count(ItemsApi::class, 'pages'));
        $ids = array_map(static fn(ItemDto $item): int => $item->id, iterator_to_array($items, false));

        self::assertSame([1, 2, 3], $ids);
        self::assertSame(2, $mock->count(ItemsApi::class, 'pages'));
        self::assertStringEndsWith('?page=2&size=2', $mock->calls()[1]->request->url);
    }

    #[Test]
    public function cursorIsReadFromTheDocument(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'cursor')->respondWithJson(['items' => [['id' => 1], ['id' => 2]], 'next' => 'abc'])->respondWithJson(['items' => [['id' => 3]], 'next' => null]);

        self::assertCount(3, iterator_to_array($this->api($mock)->cursor(), false));
        self::assertStringContainsString('cursor=abc', $mock->calls()[1]->request->url);
    }

    #[Test]
    public function aCursorThatDoesNotAdvanceEndsTheIteration(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'cursor')->respondWithJson(['items' => [['id' => 1], ['id' => 2]], 'next' => 'abc']);

        iterator_to_array($this->api($mock)->cursor(), false);

        self::assertSame(2, $mock->count(ItemsApi::class, 'cursor'));
    }

    #[Test]
    public function aNextLinkPointingToTheCurrentPageEndsTheIteration(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'linked')->respondWithJson([['id' => 1], ['id' => 2]], 200, ['link' => '<https://api.example.com/v1/items/linked?after=2>; rel="next"']);

        iterator_to_array($this->api($mock)->linked(), false);

        self::assertSame(2, $mock->count(ItemsApi::class, 'linked'));
    }

    #[Test]
    public function linkHeaderPointingToAForeignHostIsBlocked(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'linked')->respondWithJson([['id' => 1], ['id' => 2]], 200, ['link' => '<https://evil.example.org/steal>; rel="next"']);

        $this->expectException(BlockedRequest::class);
        iterator_to_array($this->api($mock)->linked(), false);
    }

    #[Test]
    public function linkHeaderToAnAllowedHostIsFollowed(): void
    {
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'linked')
            ->respondWithJson([['id' => 1], ['id' => 2]], 200, ['link' => '<https://cdn.example.com/v1/items/linked?after=2>; rel="next"'])
            ->respondWithJson([['id' => 3]]);

        self::assertCount(3, iterator_to_array($this->api($mock)->linked(), false));
        self::assertSame('https://cdn.example.com/v1/items/linked?after=2', $mock->calls()[1]->request->url);
    }

    private function api(MockTransport $mock): ItemsApi
    {
        return Outbound::create([ItemsApi::class])->withTransport($mock)->withClock(new FakeClock())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))->api(ItemsApi::class);
    }
}
