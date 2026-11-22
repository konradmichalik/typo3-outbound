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

use KonradMichalik\Typo3Outbound\Testing\{MockTransport, OutboundTestTrait};
use KonradMichalik\Typo3Outbound\Transport\{SymfonyTransport, Transport};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

/**
 * OutboundTestTraitTest.
 *
 * Proves on a dumped, compiled Symfony container (as TYPO3 uses it) that
 * replacing the public Transport alias reaches services created afterwards.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(OutboundTestTrait::class)]
final class OutboundTestTraitTest extends TestCase
{
    use OutboundTestTrait;

    private ContainerInterface $container;

    #[Test]
    public function mockReplacesTheTransportOfLaterCreatedServices(): void
    {
        $this->container = self::compiledContainer();

        $this->outboundTransport();

        $consumer = $this->container->get('consumer');
        self::assertInstanceOf(TransportConsumer::class, $consumer);
        self::assertInstanceOf(MockTransport::class, $consumer->transport);
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    private static function compiledContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->register(SymfonyTransport::class, SymfonyTransport::class)->setFactory([SymfonyTransport::class, 'create']);
        $builder->setAlias(Transport::class, SymfonyTransport::class)->setPublic(true);
        $builder->register('consumer', TransportConsumer::class)->addArgument(new Reference(Transport::class))->setPublic(true);
        $builder->compile();
        $class = 'OutboundTraitContainer'.bin2hex(random_bytes(4));
        eval('?>'.(new PhpDumper($builder))->dump(['class' => $class]));

        return new $class();
    }
}

/**
 * @internal
 */
final readonly class TransportConsumer
{
    public function __construct(public Transport $transport) {}
}
