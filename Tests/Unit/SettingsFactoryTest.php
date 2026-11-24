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

use KonradMichalik\Outbound\Runtime\Settings;
use KonradMichalik\Typo3Outbound\Configuration\SettingsFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * SettingsFactoryTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(SettingsFactory::class)]
final class SettingsFactoryTest extends TestCase
{
    #[Test]
    public function extensionConfigurationValuesBecomeSettings(): void
    {
        $configuration = $this->createMock(ExtensionConfiguration::class);
        $configuration->expects(self::once())->method('get')->with('typo3_outbound')->willReturn(['webTimeout' => '1.5', 'maxResponseBytes' => '1024']);

        $settings = (new SettingsFactory($configuration))->create();

        self::assertSame(1.5, $settings->webTimeout);
        self::assertSame(1024, $settings->maxResponseBytes);
        self::assertSame((new Settings())->cliTimeout, $settings->cliTimeout);
    }

    #[Test]
    public function aMissingExtensionConfigurationFallsBackToDefaults(): void
    {
        $configuration = $this->createMock(ExtensionConfiguration::class);
        $configuration->method('get')->willThrowException(new RuntimeException('not configured'));

        self::assertEquals(new Settings(), (new SettingsFactory($configuration))->create());
    }

    #[Test]
    public function aNonArrayConfigurationFallsBackToDefaults(): void
    {
        $configuration = $this->createMock(ExtensionConfiguration::class);
        $configuration->method('get')->willReturn('unexpected');

        self::assertEquals(new Settings(), (new SettingsFactory($configuration))->create());
    }
}
