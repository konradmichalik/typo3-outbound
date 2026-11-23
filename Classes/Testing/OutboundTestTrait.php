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

namespace KonradMichalik\Typo3Outbound\Testing;

use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\After;

use function method_exists;
use function sprintf;

/**
 * OutboundTestTrait.
 *
 * Routes every outbound call of a TYPO3 functional test to a MockTransport, so
 * the test mocks endpoints by interface and method, never by URL:
 *
 *     final class ContactControllerTest extends FunctionalTestCase
 *     {
 *         use OutboundTestTrait;
 *
 *         public function showsContact(): void
 *         {
 *             $this->mockEndpoint(ContactsApi::class, 'find', ['id' => 42])->respondWithFixture(__DIR__.'/contact.json');
 *             // ... execute a frontend request ...
 *             $this->assertEndpointCalled(ContactsApi::class, 'find');
 *         }
 *     }
 *
 * The mock also reaches frontend subrequests, which boot their own container.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
trait OutboundTestTrait
{
    private ?MockTransport $outboundTransport = null;

    /**
     * @param class-string         $interface
     * @param array<string, mixed> $arguments
     */
    protected function mockEndpoint(string $interface, string $method, array $arguments = []): MockExpectation
    {
        return $this->outboundTransport()->on($interface, $method, $arguments);
    }

    /**
     * @param class-string $interface
     */
    protected function assertEndpointCalled(string $interface, string $method, int $times = 1): void
    {
        $actual = $this->outboundTransport()->count($interface, $method);
        Assert::assertSame($times, $actual, sprintf('Expected %s::%s to be called %d times, it was called %d times.', $interface, $method, $times, $actual));
    }

    protected function outboundTransport(): MockTransport
    {
        if (null === $this->outboundTransport) {
            if (!method_exists($this, 'getContainer')) {
                throw new LogicException('OutboundTestTrait needs a TYPO3 functional test, e.g. typo3/testing-framework\'s FunctionalTestCase. In unit tests, compose the runtime with Outbound::create() and withTransport() with a MockTransport.', 1794700220);
            }
            $this->outboundTransport = new MockTransport();
            SwitchableTransport::override($this->outboundTransport);
        }

        return $this->outboundTransport;
    }

    #[After]
    protected function resetOutboundTransport(): void
    {
        SwitchableTransport::override(null);
        $this->outboundTransport = null;
    }
}
