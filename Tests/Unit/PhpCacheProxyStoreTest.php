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

use KonradMichalik\Typo3Outbound\Proxy\PhpCacheProxyStore;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;

/**
 * PhpCacheProxyStoreTest.
 *
 * PhpFrontend prepends its own opening tag, so the one of the generated proxy has to go.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(PhpCacheProxyStore::class)]
final class PhpCacheProxyStoreTest extends TestCase
{
    #[Test]
    #[DataProvider('codes')]
    public function theOpeningTagIsStrippedBeforeSaving(string $code, string $expected): void
    {
        $cache = $this->createMock(PhpFrontend::class);
        $cache->expects(self::once())->method('set')->with('PetApi_proxy', $expected);

        (new PhpCacheProxyStore($cache))->save('PetApi_proxy', $code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function codes(): iterable
    {
        yield 'with opening tag' => ["<?php\nfinal class A {}", "\nfinal class A {}"];
        yield 'without opening tag' => ['final class A {}', 'final class A {}'];
        yield 'tag in the middle is kept' => ["x<?php\n", "x<?php\n"];
    }

    #[Test]
    public function presenceIsAskedFromTheCache(): void
    {
        $cache = $this->createMock(PhpFrontend::class);
        $cache->method('has')->willReturnMap([['known', true], ['unknown', false]]);
        $store = new PhpCacheProxyStore($cache);

        self::assertTrue($store->has('known'));
        self::assertFalse($store->has('unknown'));
    }

    #[Test]
    public function loadingRequiresTheCachedFileOnce(): void
    {
        $cache = $this->createMock(PhpFrontend::class);
        $cache->expects(self::once())->method('requireOnce')->with('PetApi_proxy');

        (new PhpCacheProxyStore($cache))->load('PetApi_proxy');
    }
}
