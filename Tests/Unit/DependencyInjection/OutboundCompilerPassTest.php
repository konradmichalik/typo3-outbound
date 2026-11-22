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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\DependencyInjection;

use KonradMichalik\OutboundPetstore\Api\{PetApi, PetstoreApi, StoreApi};
use KonradMichalik\Typo3Outbound\DependencyInjection\{OutboundCompilerPass, OutboundServices};
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Proxy\ProxyFactory;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\Percent\PercentApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};

/**
 * OutboundCompilerPassTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(OutboundCompilerPass::class)]
#[CoversClass(OutboundServices::class)]
final class OutboundCompilerPassTest extends TestCase
{
    #[Test]
    public function discoveredInterfacesBecomeProxyServicesWithBakedMetadata(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Registry::class, Registry::class);
        $registered = OutboundServices::discover($builder, __DIR__.'/../../Functional/Fixtures/Extensions/outbound_petstore/Classes/Api');

        (new OutboundCompilerPass())->process($builder);

        self::assertContains(PetApi::class, $registered);
        self::assertContains(PetstoreApi::class, $registered);
        $definition = $builder->getDefinition(StoreApi::class);
        self::assertInstanceOf(Reference::class, $definition->getFactory()[0] ?? null);
        self::assertSame([StoreApi::class], $definition->getArguments());
        $export = $builder->getDefinition(Registry::class)->getArgument(0);
        self::assertIsArray($export);
        self::assertSame('/pet/{petId}', Registry::fromExport($export)->endpoint(PetApi::class, 'find')->path);
    }

    #[Test]
    public function percentSignsInAttributeValuesSurviveContainerCompilation(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Registry::class, Registry::class)->setPublic(true);
        $builder->register(ProxyFactory::class)->setSynthetic(true);
        OutboundServices::discover($builder, __DIR__.'/../Fixtures/Percent');
        $builder->addCompilerPass(new OutboundCompilerPass());

        $builder->compile();

        $registry = $builder->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertSame('/discount/100%%/%foo%', $registry->endpoint(PercentApi::class, 'discount')->path);
    }
}
