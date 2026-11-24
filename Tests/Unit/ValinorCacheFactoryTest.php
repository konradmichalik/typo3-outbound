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
use KonradMichalik\Typo3Outbound\Mapping\ValinorCacheFactory;
use PHPUnit\Framework\Attributes\{CoversClass, PreserveGlobalState, RunTestsInSeparateProcesses, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * ValinorCacheFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ValinorCacheFactory::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ValinorCacheFactoryTest extends TestCase
{
    private string $varPath;

    protected function setUp(): void
    {
        $this->varPath = sys_get_temp_dir().'/outbound_var_'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        GeneralUtility::rmdir($this->varPath, true);
    }

    #[Test]
    public function generatedCodeLandsInTheOutboundDirectoryBelowVarCache(): void
    {
        $this->initializeEnvironment('Production');

        ValinorCacheFactory::create()->set('mapper', new CacheEntry('return 1;'));

        self::assertFileExists($this->varPath.'/cache/code/outbound_valinor/mapper.php');
    }

    #[Test]
    public function productionTrustsTheGeneratedCode(): void
    {
        $this->initializeEnvironment('Production');

        self::assertNotInstanceOf(FileWatchingCache::class, ValinorCacheFactory::create());
    }

    #[Test]
    public function developmentWatchesTheDtoFilesForChanges(): void
    {
        $this->initializeEnvironment('Development');

        self::assertInstanceOf(FileWatchingCache::class, ValinorCacheFactory::create());
    }

    private function initializeEnvironment(string $context): void
    {
        Environment::initialize(new ApplicationContext($context), true, true, '/tmp/project', '/tmp/project/public', $this->varPath, '/tmp/project/config', '/tmp/project/public/index.php', 'UNIX');
    }
}
