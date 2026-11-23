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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Security;

use KonradMichalik\Typo3Outbound\Auth\{Authenticator, TokenProvider};
use KonradMichalik\Typo3Outbound\Command\{CallCommand, EndpointLocator};
use KonradMichalik\Typo3Outbound\Context\{SapiExecutionContext, StaticCallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Exception\{ApiTransportFailed, ApiValidationFailed, BlockedRequest, InvalidDefinition};
use KonradMichalik\Typo3Outbound\Http\RequestBuilder;
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Resilience\InMemoryStateStore;
use KonradMichalik\Typo3Outbound\Runtime\{CallFactory, RequestBudget, Settings};
use KonradMichalik\Typo3Outbound\Support\Redactor;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{KeyedApi, SiteTokenUrlWithoutHostsApi, SiteUriWithoutHostsResource, StaticHeaderApi, TenantApi};
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use KonradMichalik\Typo3Outbound\Typo3\RequestContext;
use PHPUnit\Framework\Attributes\{CoversNothing, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

use function sprintf;
use function strlen;

require_once __DIR__.'/../Fixtures/SecurityDefinitions.php';

/**
 * SecurityTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('OUTBOUND_TEST_KEY=top-secret-key');
    }

    #[Test]
    public function theExceptionChainDoesNotLeakQueryCredentialsEither(): void
    {
        $transport = new class implements Transport {
            public function request(Call $call): ResponseInterface
            {
                throw new TransportException(sprintf('Host "keyed.example.com" is blocked for "%s".', $call->request->url), 4121437460);
            }
        };

        try {
            $this->outbound([KeyedApi::class])->withTransport($transport)->api(KeyedApi::class)->file('report');
            self::fail('Expected a blocked request.');
        } catch (BlockedRequest $exception) {
            for ($previous = $exception->getPrevious(); null !== $previous; $previous = $previous->getPrevious()) {
                self::assertStringNotContainsString('top-secret-key', $previous->getMessage());
            }
        }
    }

    #[Test]
    public function staticHeadersFromSecretsAreMarkedSensitive(): void
    {
        $mock = new MockTransport();
        $mock->on(StaticHeaderApi::class, 'a')->respondWith(200, 'ok', ['content-type' => 'text/plain']);

        $this->outbound([StaticHeaderApi::class])->withTransport($mock)->api(StaticHeaderApi::class)->a();

        $request = $mock->calls()[0]->request;
        self::assertContains('X-Static', $request->sensitiveKeys);
        self::assertNotContains('X-Plain', $request->sensitiveKeys);
        self::assertSame(Redactor::MASK, Redactor::headers($request->headers, $request->sensitiveKeys)['X-Static']);
        self::assertSame('visible', Redactor::headers($request->headers, $request->sensitiveKeys)['X-Plain']);
    }

    #[Test]
    public function transportErrorsDoNotLeakQueryCredentials(): void
    {
        $transport = new class implements Transport {
            public function request(Call $call): ResponseInterface
            {
                throw new TransportException(sprintf('Host "keyed.example.com" is blocked for "%s".', $call->request->url), 6368868422);
            }
        };

        try {
            $this->outbound([KeyedApi::class])->withTransport($transport)->api(KeyedApi::class)->file('report');
            self::fail('Expected a blocked request.');
        } catch (BlockedRequest $exception) {
            self::assertStringNotContainsString('top-secret-key', $exception->getMessage());
            self::assertStringContainsString('api_key='.Redactor::MASK, $exception->getMessage());
        }
    }

    #[Test]
    public function lazyTransportErrorsDoNotLeakQueryCredentials(): void
    {
        $mock = new MockTransport();
        $mock->on(KeyedApi::class, 'file')->failWith('Could not resolve host for "https://keyed.example.com/files/report?api_key=top-secret-key".');

        try {
            $this->outbound([KeyedApi::class])->withTransport($mock)->api(KeyedApi::class)->file('report');
            self::fail('Expected a transport failure.');
        } catch (ApiTransportFailed $exception) {
            self::assertStringNotContainsString('top-secret-key', $exception->getMessage());
        }
    }

    #[Test]
    #[DataProvider('dotSegments')]
    public function dotSegmentsAsPathValuesAreRejected(string $value): void
    {
        $this->expectExceptionCode(1794700123);

        $this->outbound([KeyedApi::class])->withTransport(new MockTransport())->api(KeyedApi::class)->file($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dotSegments(): iterable
    {
        yield 'parent directory' => ['..'];
        yield 'current directory' => ['.'];
    }

    #[Test]
    public function slashesInPathValuesStayInsideTheirSegment(): void
    {
        $mock = new MockTransport();
        $mock->on(KeyedApi::class, 'file')->respondWith(200, 'ok', ['content-type' => 'text/plain']);

        $this->outbound([KeyedApi::class])->withTransport($mock)->api(KeyedApi::class)->file('../../admin?x=1');

        self::assertStringStartsWith('https://keyed.example.com/files/..%2F..%2Fadmin%3Fx%3D1?', $mock->calls()[0]->request->url);
    }

    /**
     * @param class-string $interface
     */
    #[Test]
    #[DataProvider('urlsFromSiteSettings')]
    public function urlsFromSiteSettingsNeedAllowedHosts(string $interface, int $code): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionCode($code);

        Registry::build([$interface]);
    }

    /**
     * @return iterable<string, array{class-string, int}>
     */
    public static function urlsFromSiteSettings(): iterable
    {
        yield 'base URI' => [SiteUriWithoutHostsResource::class, 1794700036];
        yield 'token URL' => [SiteTokenUrlWithoutHostsApi::class, 1794700037];
    }

    #[Test]
    public function tokenUrlFromSiteSettingsMustMatchAllowedHosts(): void
    {
        $mock = new MockTransport();
        $context = new StaticCallContext('tenant', ['tenant.baseUri' => 'https://api.example.com', 'tenant.tokenUrl' => 'https://evil.example.org/token']);

        try {
            $this->outbound([TenantApi::class])->withTransport($mock)->withCallContext($context)->api(TenantApi::class)->a();
            self::fail('Expected the token URL to be blocked.');
        } catch (BlockedRequest $exception) {
            self::assertStringContainsString('evil.example.org', $exception->getMessage());
        }
        self::assertSame([], $mock->calls());
    }

    #[Test]
    public function aPortInATokenUrlFromSiteSettingsMustBeAllowed(): void
    {
        $mock = new MockTransport();
        $context = new StaticCallContext('tenant', ['tenant.baseUri' => 'https://api.example.com', 'tenant.tokenUrl' => 'https://api.example.com:9999/token']);

        try {
            $this->outbound([TenantApi::class])->withTransport($mock)->withCallContext($context)->api(TenantApi::class)->a();
            self::fail('Expected the token URL to be blocked.');
        } catch (BlockedRequest $exception) {
            self::assertStringContainsString(':9999', $exception->getMessage());
        }
        self::assertSame([], $mock->calls());
    }

    #[Test]
    public function aPortInABaseUriFromSiteSettingsMustBeAllowed(): void
    {
        $mock = new MockTransport();
        $mock->onKey('tenant::token')->respondWithJson(['access_token' => 't', 'expires_in' => 3600]);
        $context = new StaticCallContext('tenant', ['tenant.baseUri' => 'https://api.example.com:9999', 'tenant.tokenUrl' => 'https://api.example.com/token']);

        try {
            $this->outbound([TenantApi::class])->withTransport($mock)->withCallContext($context)->api(TenantApi::class)->a();
            self::fail('Expected the base URI to be blocked.');
        } catch (BlockedRequest $exception) {
            self::assertStringContainsString(':9999', $exception->getMessage());
        }
    }

    #[Test]
    public function errorTextFromTheApiIsBoundedAndSingleLine(): void
    {
        $mock = new MockTransport();
        $mock->on(KeyedApi::class, 'file')->respondWithJson(['title' => "Bad\nrequest\r\n".str_repeat('x', 5000)], 400, ['content-type' => 'application/problem+json']);

        try {
            $this->outbound([KeyedApi::class])->withTransport($mock)->api(KeyedApi::class)->file('a');
            self::fail('Expected a validation failure.');
        } catch (ApiValidationFailed $exception) {
            self::assertLessThan(300, strlen($exception->getMessage()));
            self::assertStringNotContainsString("\n", $exception->getMessage());
        }
    }

    #[Test]
    public function redactorMasksCredentialsInTheAuthority(): void
    {
        self::assertSame('https://'.Redactor::MASK.'@api.example.com/a?token='.Redactor::MASK, Redactor::url('https://user:pass@api.example.com/a?token=abc'));
        self::assertSame('https://api.example.com/a@b', Redactor::url('https://api.example.com/a@b'));
    }

    #[Test]
    public function proxiesAreNotLoadedFromADirectoryOthersCanWrite(): void
    {
        $directory = sys_get_temp_dir().'/outbound-unsafe-'.bin2hex(random_bytes(4));
        mkdir($directory);
        chmod($directory, 0o777);
        $store = new FileProxyStore($directory);
        $store->save('Proxy', "<?php\n");

        $this->expectExceptionCode(1794700164);
        $store->load('Proxy');
    }

    #[Test]
    public function dryRunMasksASensitiveBody(): void
    {
        $context = new StaticCallContext();
        $values = new ValueResolver($context);
        $store = new InMemoryStateStore();
        $clock = new FakeClock();
        $execution = new SapiExecutionContext(false);
        $settings = new Settings();
        $mock = new MockTransport();
        $tokens = new TokenProvider($mock, $store, $values, $context, $clock, new RequestBudget($settings, $execution, $clock));
        $calls = new CallFactory(new RequestBuilder($values, $context), new Authenticator($values, $tokens, $context), $store, $execution, $settings);
        $outbound = $this->outbound([KeyedApi::class])->withTransport($mock);
        $requestContext = new RequestContext((new ReflectionClass(SiteFinder::class))->newInstanceWithoutConstructor());
        $tester = new CommandTester(new CallCommand($outbound->registry(), new EndpointLocator($outbound->registry()), $outbound->invoker(), $calls, $requestContext));

        $tester->execute(['endpoint' => 'KeyedApi::login', 'arguments' => ['credentials={"password":"hunter2"}'], '--dry-run' => true]);

        self::assertStringNotContainsString('hunter2', $tester->getDisplay());
        self::assertStringNotContainsString('top-secret-key', $tester->getDisplay());
        self::assertStringContainsString('POST https://keyed.example.com/login?api_key='.Redactor::MASK, $tester->getDisplay());
        self::assertSame([], $mock->calls());
    }

    /**
     * @param list<class-string> $interfaces
     */
    private function outbound(array $interfaces): Outbound
    {
        return Outbound::create($interfaces)
            ->withClock(new FakeClock())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'));
    }
}
