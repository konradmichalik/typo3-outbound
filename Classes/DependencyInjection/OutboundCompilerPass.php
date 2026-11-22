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

namespace KonradMichalik\Typo3Outbound\DependencyInjection;

use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Proxy\ProxyFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};

use function array_keys;

/**
 * OutboundCompilerPass.
 *
 * Builds the registry of all tagged API interfaces while the container is
 * compiled, so an invalid declaration fails the build, and turns every interface
 * into a service created by the ProxyFactory. The metadata is baked into the
 * compiled container as plain strings; there is no runtime scanning.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class OutboundCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var list<class-string> $interfaces */
        $interfaces = array_keys($container->findTaggedServiceIds(OutboundServices::TAG));
        $registry = Registry::build($interfaces);

        if ($container->hasDefinition(Registry::class)) {
            $container->getDefinition(Registry::class)
                ->setFactory([Registry::class, 'fromExport'])
                ->setArguments([$container->getParameterBag()->escapeValue($registry->export())]);
        }

        foreach ($interfaces as $interface) {
            $container->getDefinition($interface)
                ->setFactory([new Reference(ProxyFactory::class), 'create'])
                ->setArguments([$interface])
                ->setPublic(true);
        }
    }
}
