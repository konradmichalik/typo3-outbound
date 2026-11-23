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

namespace KonradMichalik\Typo3Outbound\Configuration;

use KonradMichalik\Outbound\Runtime\Settings;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

use function is_array;

/**
 * SettingsFactory.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class SettingsFactory
{
    public function __construct(
        private ExtensionConfiguration $configuration,
    ) {}

    public function create(): Settings
    {
        try {
            $values = $this->configuration->get('typo3_outbound');
        } catch (Throwable) {
            $values = [];
        }

        /** @var array<string, mixed> $settings */
        $settings = is_array($values) ? $values : [];

        return Settings::fromArray($settings);
    }
}
