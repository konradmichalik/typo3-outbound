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

namespace KonradMichalik\Typo3Outbound\Tests\Unit;

use CuyZ\Valinor\Cache\{CacheEntry, FileWatchingCache};
use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Typo3Outbound\Mapping\ValinorCacheFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\Environment;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ValinorCacheFactory::class)]
final class ValinorCacheFactoryTest extends TestCase
{
    #[Test]
    #[WithEnvironment(context: 'Production')]
    public function generatedCodeLandsInTheOutboundDirectoryBelowVarCache(): void
    {
        ValinorCacheFactory::create()->set('mapper', new CacheEntry('return 1;'));

        self::assertFileExists(Environment::getVarPath().'/cache/code/outbound_valinor/mapper.php');
    }

    #[Test]
    #[WithEnvironment(context: 'Production')]
    public function productionTrustsTheGeneratedCode(): void
    {
        self::assertNotInstanceOf(FileWatchingCache::class, ValinorCacheFactory::create());
    }

    #[Test]
    #[WithEnvironment(context: 'Development')]
    public function developmentWatchesTheDtoFilesForChanges(): void
    {
        self::assertInstanceOf(FileWatchingCache::class, ValinorCacheFactory::create());
    }
}
