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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Testing;

use KonradMichalik\Typo3Outbound\Http\RequestSpec;
use KonradMichalik\Typo3Outbound\Testing\{MockTransport, OutboundTestTrait, SwitchableTransport};
use KonradMichalik\Typo3Outbound\Transport\{Call, SymfonyTransport, Transport, TransportOptions};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;

/**
 * OutboundTestTraitTest.
 *
 * TYPO3 functional tests boot a fresh container for every frontend subrequest,
 * so the mock has to reach containers that did not exist when it was installed.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(OutboundTestTrait::class)]
#[CoversClass(SwitchableTransport::class)]
final class OutboundTestTraitTest extends TestCase
{
    use OutboundTestTrait;

    #[Test]
    public function mockReachesAContainerBuiltAfterwards(): void
    {
        $mock = $this->outboundTransport();

        $consumer = self::compiledContainer()->get('consumer');
        self::assertInstanceOf(TransportConsumer::class, $consumer);
        $this->expectExceptionCode(1794700170);

        try {
            $consumer->transport->request(self::call());
        } finally {
            self::assertCount(1, $mock->calls());
        }
    }

    #[Test]
    public function theInnerTransportIsUsedOnceTheMockIsRemoved(): void
    {
        $this->outboundTransport();
        $this->resetOutboundTransport();
        $inner = new MockTransport();

        $this->expectExceptionCode(1794700170);

        try {
            (new SwitchableTransport($inner))->request(self::call());
        } finally {
            self::assertCount(1, $inner->calls());
        }
    }

    #[Test]
    public function outsideAContainerBasedTestTheTraitRefusesToMock(): void
    {
        $test = new class {
            use OutboundTestTrait;

            public function mock(): MockTransport
            {
                return $this->outboundTransport();
            }
        };

        $this->expectExceptionCode(1794700220);
        $test->mock();
    }

    private function getContainer(): ContainerInterface
    {
        return self::compiledContainer();
    }

    private static function call(): Call
    {
        return new Call('PetApi::find', [], new RequestSpec('GET', 'https://api.example.com/pets/1'), new TransportOptions(5.0, 1024));
    }

    private static function compiledContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->register(SymfonyTransport::class, SymfonyTransport::class)->setFactory([SymfonyTransport::class, 'create']);
        $builder->setAlias(Transport::class, SymfonyTransport::class);
        $builder->register(SwitchableTransport::class, SwitchableTransport::class)
            ->setDecoratedService(Transport::class)
            ->addArgument(new Reference('.inner'));
        $builder->register('consumer', TransportConsumer::class)->addArgument(new Reference(Transport::class))->setPublic(true);
        $builder->compile();
        $class = 'OutboundTraitContainer'.bin2hex(random_bytes(4));
        $code = (new PhpDumper($builder))->dump(['class' => $class]);
        self::assertIsString($code);
        eval('?>'.$code);
        $container = new $class();
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}

/**
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class TransportConsumer
{
    public function __construct(public Transport $transport) {}
}
