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

use KonradMichalik\Typo3Outbound\Exception\ApiMappingFailed;
use KonradMichalik\Typo3Outbound\Mapping\ValinorMapper;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{RawArraysApi, StrictRawArraysApi};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/RawDefinitions.php';

/**
 * RawArrayMappingTest.
 *
 * Responses handed on unchanged to existing code are typed as arrays of mixed values,
 * which a connection allows with permissiveTypes; every other connection stays strict.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ValinorMapper::class)]
final class RawArrayMappingTest extends TestCase
{
    #[Test]
    public function aDtoWithAListOfRawArraysIsMapped(): void
    {
        $item = ['a' => 1, 'b' => ['c' => [1, 2]], 'd' => null];
        $mock = new MockTransport();
        $mock->on(RawArraysApi::class, 'search')->respondWithJson(['content' => [$item], 'totalPages' => 1]);

        $page = $this->api($mock)->search();

        self::assertSame([$item], $page->content);
        self::assertSame(1, $page->totalPages);
    }

    #[Test]
    public function aRawObjectReturnTypeIsMapped(): void
    {
        $mock = new MockTransport();
        $mock->on(RawArraysApi::class, 'object')->respondWithJson(['mediaItems' => [['position' => 1]]]);

        self::assertSame(['mediaItems' => [['position' => 1]]], $this->api($mock)->object());
    }

    #[Test]
    public function aConnectionWithoutTheOptInStaysStrict(): void
    {
        $mock = new MockTransport();
        $mock->on(StrictRawArraysApi::class, 'search')->respondWithJson(['content' => [['a' => 1]], 'totalPages' => 1]);
        $api = Outbound::create([StrictRawArraysApi::class])
            ->withTransport($mock)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(StrictRawArraysApi::class);

        $this->expectException(ApiMappingFailed::class);

        $api->search();
    }

    private function api(MockTransport $mock): RawArraysApi
    {
        return Outbound::create([RawArraysApi::class])
            ->withTransport($mock)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(RawArraysApi::class);
    }
}
