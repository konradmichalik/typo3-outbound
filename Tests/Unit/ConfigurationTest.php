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

use KonradMichalik\Typo3Outbound\Configuration\Configuration;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Backend\{SimpleFileBackend, Typo3DatabaseBackend};
use TYPO3\CMS\Core\Cache\Frontend\{PhpFrontend, VariableFrontend};

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    private mixed $confVars = null;

    protected function setUp(): void
    {
        $this->confVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        unset($GLOBALS['TYPO3_CONF_VARS']);
    }

    protected function tearDown(): void
    {
        if (null === $this->confVars) {
            unset($GLOBALS['TYPO3_CONF_VARS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS'] = $this->confVars;
        }
    }

    #[Test]
    public function allThreeCachesAreRegisteredWithTheirBackendAndGroups(): void
    {
        Configuration::registerCaches();

        self::assertSame([
            'outbound' => ['frontend' => VariableFrontend::class, 'backend' => Typo3DatabaseBackend::class, 'groups' => ['pages', 'all']],
            'outbound_state' => ['frontend' => VariableFrontend::class, 'backend' => Typo3DatabaseBackend::class, 'groups' => ['system']],
            'outbound_proxies' => ['frontend' => PhpFrontend::class, 'backend' => SimpleFileBackend::class, 'groups' => ['system']],
        ], $this->registeredCaches());
    }

    #[Test]
    #[DataProvider('cacheIdentifiers')]
    public function aCacheConfiguredByTheProjectIsNotOverwritten(string $identifier): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['caching' => ['cacheConfigurations' => [$identifier => ['backend' => 'ProjectBackend']]]]];

        Configuration::registerCaches();

        self::assertSame(['backend' => 'ProjectBackend'], $this->registeredCaches()[$identifier]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cacheIdentifiers(): iterable
    {
        yield 'outbound' => ['outbound'];
        yield 'outbound_state' => ['outbound_state'];
        yield 'outbound_proxies' => ['outbound_proxies'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function registeredCaches(): array
    {
        /** @var array{SYS: array{caching: array{cacheConfigurations: array<string, array<string, mixed>>}}} $confVars */
        $confVars = $GLOBALS['TYPO3_CONF_VARS'];

        return $confVars['SYS']['caching']['cacheConfigurations'];
    }
}
