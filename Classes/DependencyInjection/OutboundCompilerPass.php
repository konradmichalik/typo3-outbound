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
use function is_string;
use function str_starts_with;

/**
 * OutboundCompilerPass.
 *
 * Registers the API interfaces a resource scan left as abstract definitions.
 * Then builds the registry of all tagged API interfaces while the container is
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
        $this->registerScannedInterfaces($container);
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

    /**
     * Symfony keeps scanned interfaces as ".abstract." definitions until the removing
     * passes run. Exclude paths are skipped: their classes were never loaded and may not load.
     */
    private function registerScannedInterfaces(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, '.abstract.') || !$definition->hasTag('container.excluded')) {
                continue;
            }
            $class = $definition->getClass();
            $reflection = is_string($class) ? $container->getReflectionClass($class, false) : null;
            if (null !== $reflection && OutboundServices::isApi($reflection)) {
                OutboundServices::register($container, $reflection->getName());
            }
        }
    }
}
