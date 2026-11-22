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

use KonradMichalik\Typo3Outbound\Exception\{ApiException, ApiTransportFailed, BlockedRequest};
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{IntranetApi, PrivateAddressApi, SmallLimitApi};
use KonradMichalik\Typo3Outbound\Transport\SymfonyTransport;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

require_once __DIR__.'/../Fixtures/SecurityDefinitions.php';

/**
 * TransportGuardsTest.
 *
 * The documented safety limits of the real transport: response size, redirects and the private network block.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(SymfonyTransport::class)]
final class TransportGuardsTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    private array $requests = [];

    #[Test]
    public function aResponseOverTheLimitFailsAsATransportError(): void
    {
        $api = $this->api(SmallLimitApi::class, static fn(): MockResponse => new MockResponse(str_repeat('a', 5000), ['response_headers' => ['content-type: text/plain']]));

        $this->expectException(ApiTransportFailed::class);
        $api->blob();
    }

    #[Test]
    public function aResponseWithinTheLimitIsReturned(): void
    {
        $api = $this->api(SmallLimitApi::class, static fn(): MockResponse => new MockResponse(str_repeat('a', 50), ['response_headers' => ['content-type: text/plain']]));

        self::assertSame(str_repeat('a', 50), $api->blob());
    }

    #[Test]
    public function redirectsAreNeverFollowed(): void
    {
        $api = $this->api(SmallLimitApi::class, static fn(): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://evil.example.org/']]));

        try {
            $api->blob();
            self::fail('A redirect is not a successful answer.');
        } catch (ApiException) {
        }

        self::assertCount(1, $this->requests);
        self::assertSame(0, $this->requests[0]['options']['max_redirects']);
    }

    #[Test]
    public function aPrivateAddressIsBlockedBeforeAnythingIsSent(): void
    {
        $api = $this->api(PrivateAddressApi::class, static fn(): MockResponse => new MockResponse('secret', ['response_headers' => ['content-type: text/plain']]));

        try {
            $api->a();
            self::fail('The private network must be blocked.');
        } catch (BlockedRequest) {
        }

        self::assertSame([], $this->requests);
    }

    #[Test]
    public function aConnectionCanOptOutForIntranetApis(): void
    {
        $api = $this->api(IntranetApi::class, static fn(): MockResponse => new MockResponse('intranet', ['response_headers' => ['content-type: text/plain']]));

        self::assertSame('intranet', $api->a());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    private function api(string $interface, \Closure $respond): object
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($respond): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return $respond();
        });

        return Outbound::create([$interface])
            ->withClock(new FakeClock())
            ->withTransport(new SymfonyTransport($client))
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api($interface);
    }
}
