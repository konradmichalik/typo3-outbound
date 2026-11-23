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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Metadata;

use KonradMichalik\Typo3Outbound\Attribute\Auth\TokenLogin;
use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use KonradMichalik\Typo3Outbound\Metadata\{MetadataFactory, Registry};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures as F;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/InvalidValueDefinitions.php';
require_once __DIR__.'/../Fixtures/CacheDefinitions.php';
require_once __DIR__.'/../Fixtures/TokenLoginDefinitions.php';

/**
 * AttributeValueValidationTest.
 *
 * Values that would silently misbehave at runtime (a lifetime of 0 means "never expires"
 * in the database cache backend, a threshold of 0 trips the breaker at once) fail the build.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(MetadataFactory::class)]
final class AttributeValueValidationTest extends TestCase
{
    /**
     * @param class-string $interface
     */
    #[Test]
    #[DataProvider('invalidDefinitions')]
    public function invalidValuesFailTheBuild(string $interface, int $code): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionCode($code);

        Registry::build([$interface]);
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'cache lifetime 0' => [F\ZeroLifetimeApi::class, 1794700230];
        yield 'negative cache lifetime' => [F\NegativeLifetimeApi::class, 1794700230];
        yield 'negative staleOnError' => [F\NegativeStaleApi::class, 1794700230];
        yield 'timeout 0' => [F\ZeroTimeoutApi::class, 1794700231];
        yield 'negative timeout' => [F\NegativeTimeoutApi::class, 1794700231];
        yield 'negative retries' => [F\NegativeRetriesApi::class, 1794700232];
        yield 'shrinking backoff' => [F\ShrinkingBackoffApi::class, 1794700232];
        yield 'negative delay' => [F\NegativeDelayApi::class, 1794700232];
        yield 'retry on a non-HTTP status' => [F\BadRetryStatusApi::class, 1794700232];
        yield 'page size 0' => [F\ZeroPageSizeApi::class, 1794700233];
        yield 'maxPages 0' => [F\ZeroMaxPagesApi::class, 1794700233];
        yield 'cursor style without cursorPath' => [F\CursorWithoutPathApi::class, 1794700236];
        yield 'breaker threshold 0' => [F\ZeroThresholdBreakerApi::class, 1794700234];
        yield 'breaker cooldown 0' => [F\ZeroCooldownBreakerApi::class, 1794700234];
        yield 'placeholder that is not a name' => [F\BracedPathApi::class, 1794700235];
        yield 'rate limit interval that is garbage' => [F\GarbageIntervalLimitApi::class, 1794700009];
        yield 'rate limit interval of zero' => [F\ZeroIntervalLimitApi::class, 1794700009];
        yield 'login body empty' => [F\EmptyBodyLoginApi::class, 1794700237];
        yield 'login body without field names' => [F\ListBodyLoginApi::class, 1794700237];
        yield 'login token field empty' => [F\EmptyTokenFieldLoginApi::class, 1794700238];
        yield 'login ttl 0' => [F\ZeroTtlLoginApi::class, 1794700239];
        yield 'login ttl within the expiry margin' => [F\MarginTtlLoginApi::class, 1794700239];
        yield 'login URL over plain http' => [F\PlainHttpLoginApi::class, 1794700240];
        yield 'login URL from a site setting without allowedHosts' => [F\SiteLoginUrlWithoutHostsApi::class, 1794700241];
    }

    #[Test]
    public function aValidTokenLoginBuilds(): void
    {
        $auth = Registry::build([F\TokenLoginApi::class])->connectionOf(F\TokenLoginApi::class)->auth;

        self::assertInstanceOf(TokenLogin::class, $auth);
        self::assertSame(600, $auth->ttl);
    }

    #[Test]
    public function sensibleValuesStillBuild(): void
    {
        $registry = Registry::build([F\ProductsApi::class, F\ItemsApi::class]);

        self::assertSame(60, $registry->endpoint(F\ProductsApi::class, 'find')->cache?->lifetime);
    }
}
