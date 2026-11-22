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

use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use KonradMichalik\Typo3Outbound\Metadata\{Naming, Registry, ReturnKind};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{BodyOnGetApi, CachedPostApi, GeneratorApi, ExtendsWithEndpointsApi, InsecureApi, ItemDto, ItemsApi, NoRoleApi, OrphanApi, PaginateWithoutIterableApi, UnboundPlaceholderApi};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';
require_once __DIR__.'/../Fixtures/GeneratorDefinitions.php';

/**
 * MetadataFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(\KonradMichalik\Typo3Outbound\Metadata\MetadataFactory::class)]
#[CoversClass(Registry::class)]
#[CoversClass(Naming::class)]
final class MetadataFactoryTest extends TestCase
{
    #[Test]
    public function precedenceIsMethodOverResourceOverConnection(): void
    {
        $registry = Registry::build([ItemsApi::class]);
        $find = $registry->endpoint(ItemsApi::class, 'find');
        $pages = $registry->endpoint(ItemsApi::class, 'pages');

        self::assertSame('my_crm', $find->connection);
        self::assertSame('/items/{id}', $find->path);
        self::assertSame(1.0, $find->timeout?->seconds);
        self::assertSame('ItemsApi::find', $find->origins['Timeout']);
        self::assertSame(2.0, $pages->timeout?->seconds);
        self::assertSame(1, $find->retry?->maxRetries);
        self::assertSame(ReturnKind::Iterable, $pages->returnType->kind);
        self::assertSame(ItemDto::class, $pages->returnType->signature);
    }

    #[Test]
    #[DataProvider('generatorMethods')]
    public function theItemTypeOfAGeneratorIsItsValueArgument(string $method): void
    {
        $endpoint = Registry::build([GeneratorApi::class])->endpoint(GeneratorApi::class, $method);

        self::assertSame(ReturnKind::Iterable, $endpoint->returnType->kind);
        self::assertSame(ItemDto::class, $endpoint->returnType->signature);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function generatorMethods(): iterable
    {
        yield 'all four arguments' => ['four'];
        yield 'value only' => ['one'];
        yield 'iterable with key' => ['keyed'];
    }

    #[Test]
    public function registrySurvivesExport(): void
    {
        $registry = Registry::fromExport(Registry::build([ItemsApi::class])->export());

        self::assertSame('/items/{id}', $registry->endpoint(ItemsApi::class, 'find')->path);
        self::assertSame(['my_crm'], $registry->connectionNames());
    }

    /**
     * @param class-string $interface
     */
    #[Test]
    #[DataProvider('invalidDefinitions')]
    public function invalidDefinitionsFailWithAClearCode(string $interface, int $code): void
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
        yield 'http base URI' => [InsecureApi::class, 1794700007];
        yield 'resource without connection' => [OrphanApi::class, 1794700004];
        yield 'base with endpoints and resources' => [ExtendsWithEndpointsApi::class, 1794700025];
        yield 'parameter without role' => [NoRoleApi::class, 1794700020];
        yield 'unbound placeholder' => [UnboundPlaceholderApi::class, 1794700018];
        yield 'body on GET' => [BodyOnGetApi::class, 1794700016];
        yield 'cache on POST' => [CachedPostApi::class, 1794700012];
        yield 'paginate without iterable' => [PaginateWithoutIterableApi::class, 1794700011];
    }

    #[Test]
    public function connectionNamesFollowOneRule(): void
    {
        self::assertSame('crm', Naming::connectionName('CrmApi'));
        self::assertSame('my_crm', Naming::connectionName('MyCRMApi'));
        self::assertSame('weather_service', Naming::connectionName('WeatherServiceApi'));
        self::assertSame('custom', Naming::connectionName('CrmApi', 'custom'));
    }
}
