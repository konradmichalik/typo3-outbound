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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Http;

use KonradMichalik\Typo3Outbound\Enum\QueryStyle;
use KonradMichalik\Typo3Outbound\Exception\BlockedRequest;
use KonradMichalik\Typo3Outbound\Http\{HostGuard, LinkHeader, QuerySerializer};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * HttpTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(QuerySerializer::class)]
#[CoversClass(HostGuard::class)]
#[CoversClass(LinkHeader::class)]
final class HttpTest extends TestCase
{
    #[Test]
    public function queryStylesAndSortingAreStable(): void
    {
        $query = QuerySerializer::build([
            ['sort', ['-created', 'name'], QueryStyle::CommaDelimited],
            ['filter', ['status' => 'active', 'created' => ['gte' => '2026-01-01'], 'tags' => ['vip', 'new']], QueryStyle::DeepObject],
            ['ids', [1, 2], QueryStyle::Brackets],
            ['empty', null, QueryStyle::Form],
        ]);

        self::assertSame('filter[created][gte]=2026-01-01&filter[status]=active&filter[tags]=vip%2Cnew&ids[]=1&ids[]=2&sort=-created%2Cname', $query);
    }

    #[Test]
    public function hostGuardRejectsForeignHostsAndPlainHttp(): void
    {
        HostGuard::check('https://api.example.com/a', 'https://api.example.com', [], false, 'E');
        HostGuard::check('http://intranet.local/a', 'http://intranet.local', [], true, 'E');

        $this->expectException(BlockedRequest::class);
        HostGuard::check('https://evil.example.org/a', 'https://api.example.com', [], false, 'E');
    }

    #[Test]
    public function hostGuardRejectsHttpWithoutOptOut(): void
    {
        $this->expectExceptionCode(1794700061);
        HostGuard::check('http://api.example.com/a', 'https://api.example.com', [], false, 'E');
    }

    #[Test]
    public function aNextPageLinkMayOnlyPointToAnAllowedHost(): void
    {
        HostGuard::check('https://API.example.com/a', 'https://api.example.com', [], false, 'E');
        HostGuard::check('https://cdn.example.com/a', 'https://api.example.com', ['api.example.com', 'cdn.example.com'], false, 'E');

        $this->expectExceptionCode(1794700062);
        HostGuard::check('https://api.example.com/a', 'https://cdn.example.com', ['cdn.example.com'], false, 'E');
    }

    #[Test]
    #[DataProvider('hostileLinks')]
    public function hostileLinksAreBlocked(string $link): void
    {
        $this->expectException(BlockedRequest::class);
        HostGuard::check(HostGuard::absolute($link, 'https://api.example.com/v1/items'), 'https://api.example.com', [], false, 'E');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostileLinks(): iterable
    {
        yield 'other host' => ['https://evil.example.org/x'];
        yield 'userinfo trick' => ['https://api.example.com@evil.example.org/x'];
        yield 'plain http' => ['http://api.example.com/x'];
        yield 'host that merely starts like the allowed one' => ['https://api.example.com.evil.example.org/x'];
    }

    #[Test]
    #[DataProvider('schemeLessLinks')]
    public function linksWithoutAnHttpSchemeStayOnTheCurrentHost(string $link): void
    {
        $url = HostGuard::absolute($link, 'https://api.example.com/v1/items');

        self::assertSame('api.example.com', parse_url($url, PHP_URL_HOST));
        HostGuard::check($url, 'https://api.example.com', [], false, 'E');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function schemeLessLinks(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'data' => ['data:text/plain,x'];
        yield 'ftp' => ['ftp://api.example.com/x'];
    }

    #[Test]
    public function aProtocolRelativeLinkStaysOnTheCurrentHost(): void
    {
        $url = HostGuard::absolute('//evil.example.org/x', 'https://api.example.com/v1/items');

        self::assertSame('api.example.com', parse_url($url, PHP_URL_HOST));
        HostGuard::check($url, 'https://api.example.com', [], false, 'E');
    }

    #[Test]
    public function relativeLinksAreResolved(): void
    {
        self::assertSame('https://api.example.com/v1/items?page=2', HostGuard::absolute('/v1/items?page=2', 'https://api.example.com/v1/items'));
        self::assertSame('https://api.example.com/v1/next', HostGuard::absolute('next', 'https://api.example.com/v1/items'));
    }

    #[Test]
    public function linkHeaderFindsNext(): void
    {
        self::assertSame('https://x/2', LinkHeader::next('<https://x/1>; rel="prev", <https://x/2>; rel="next"'));
        self::assertNull(LinkHeader::next('<https://x/1>; rel="prev"'));
        self::assertNull(LinkHeader::next(null));
    }
}
