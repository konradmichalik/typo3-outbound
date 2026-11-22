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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Support;

use KonradMichalik\Typo3Outbound\Support\Redactor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * RedactorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Redactor::class)]
final class RedactorTest extends TestCase
{
    #[Test]
    #[DataProvider('sensitiveQueryKeys')]
    public function sensitiveQueryKeysAreMasked(string $key): void
    {
        self::assertSame('https://a.example.com/x?'.$key.'='.Redactor::MASK.'&page=2', Redactor::url('https://a.example.com/x?'.$key.'=s3cret&page=2'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveQueryKeys(): iterable
    {
        yield 'token' => ['token'];
        yield 'upper case' => ['API_KEY'];
        yield 'array style' => ['token[]'];
        yield 'encoded array style' => ['access_token%5B%5D'];
        yield 'nested array style' => ['auth[token]'];
        yield 'jwt' => ['jwt'];
        yield 'auth' => ['auth'];
        yield 'refresh token' => ['refresh_token'];
        yield 'vendor prefix' => ['x-amz-security-token'];
        yield 'password' => ['user_password'];
        yield 'token inside brackets' => ['filter[token]'];
        yield 'encoded token inside brackets' => ['data%5Baccess_token%5D'];
        yield 'password inside nested brackets' => ['credentials[login][password]'];
    }

    #[Test]
    public function harmlessQueryKeysStayReadable(): void
    {
        self::assertSame('https://a.example.com/x?page=2&sort=name&q=tokyo&author=bob&filter[status]=open', Redactor::url('https://a.example.com/x?page=2&sort=name&q=tokyo&author=bob&filter[status]=open'));
    }

    #[Test]
    public function declaredKeysAreMaskedWhateverTheirName(): void
    {
        self::assertSame('https://a.example.com/x?code='.Redactor::MASK, Redactor::url('https://a.example.com/x?code=123', ['code']));
    }

    #[Test]
    public function declaredKeysAlsoMatchTheirArrayForm(): void
    {
        self::assertSame('https://a.example.com/x?code%5B%5D='.Redactor::MASK, Redactor::url('https://a.example.com/x?code%5B%5D=123', ['code']));
    }

    #[Test]
    public function userinfoIsMasked(): void
    {
        self::assertSame('https://'.Redactor::MASK.'@a.example.com/x', Redactor::url('https://user:pass@a.example.com/x'));
    }

    #[Test]
    #[DataProvider('sensitiveHeaders')]
    public function sensitiveHeadersAreMasked(string $name): void
    {
        self::assertSame([$name => Redactor::MASK, 'Accept' => 'application/json'], Redactor::headers([$name => 'value', 'Accept' => 'application/json']));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveHeaders(): iterable
    {
        yield 'authorization' => ['Authorization'];
        yield 'proxy authorization' => ['Proxy-Authorization'];
        yield 'cookie' => ['Cookie'];
        yield 'set cookie' => ['Set-Cookie'];
        yield 'api key' => ['X-Api-Key'];
        yield 'auth token' => ['X-Auth-Token'];
        yield 'access token' => ['X-Access-Token'];
        yield 'secret' => ['X-Client-Secret'];
    }

    #[Test]
    public function harmlessHeadersStayReadable(): void
    {
        self::assertSame(['X-Author' => 'bob', 'X-Request-Id' => '1'], Redactor::headers(['X-Author' => 'bob', 'X-Request-Id' => '1']));
    }

    #[Test]
    public function declaredHeadersAreMaskedWhateverTheirName(): void
    {
        self::assertSame(['X-Tenant' => Redactor::MASK, 'Accept' => '*/*'], Redactor::headers(['X-Tenant' => 'acme', 'Accept' => '*/*'], ['x-tenant']));
    }
}
