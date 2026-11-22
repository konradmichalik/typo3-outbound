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

use KonradMichalik\Typo3Outbound\Transport\Transport;
use PHPUnit\Framework\Assert;
use Psr\Container\ContainerInterface;

use function method_exists;
use function sprintf;

/**
 * OutboundTestTrait.
 *
 * Replaces the transport in the TYPO3 container with a MockTransport, so a
 * functional test mocks endpoints by interface and method, never by URL:
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
 * Call mockEndpoint() before the first request: the transport is replaced the
 * first time the trait is used, and services created earlier keep theirs.
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
        if (null !== $this->outboundTransport) {
            return $this->outboundTransport;
        }
        if (!method_exists($this, 'getContainer')) {
            throw new \LogicException('OutboundTestTrait needs a test case with getContainer(), e.g. typo3/testing-framework\'s FunctionalTestCase. In unit tests, compose the runtime with Outbound::create() and a MockTransport.', 1794700220);
        }
        $container = $this->getContainer();
        if (!$container instanceof ContainerInterface || !method_exists($container, 'set')) {
            throw new \LogicException('The container does not allow replacing services.', 1794700221);
        }
        $this->outboundTransport = new MockTransport();
        $container->set(Transport::class, $this->outboundTransport);

        return $this->outboundTransport;
    }
}
