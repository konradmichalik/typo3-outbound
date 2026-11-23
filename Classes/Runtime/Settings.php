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

namespace KonradMichalik\Typo3Outbound\Runtime;

/**
 * Settings.
 *
 * Global defaults of the installation. Values of a single connection never live
 * here; they belong to its interface and to Env or settings.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Settings
{
    public function __construct(
        public float $webTimeout = 3.0,
        public float $cliTimeout = 30.0,
        public float $webBudget = 8.0,
        public float $webRetryAfterCap = 1.0,
        public float $cliRetryAfterCap = 60.0,
        public int $maxResponseBytes = 5_242_880,
    ) {}

    /**
     * @param array<string, mixed> $configuration Extension configuration
     */
    public static function fromArray(array $configuration): self
    {
        $default = new self();

        return new self(
            webTimeout: self::float($configuration['webTimeout'] ?? null, $default->webTimeout),
            cliTimeout: self::float($configuration['cliTimeout'] ?? null, $default->cliTimeout),
            webBudget: self::float($configuration['webBudget'] ?? null, $default->webBudget),
            webRetryAfterCap: self::float($configuration['webRetryAfterCap'] ?? null, $default->webRetryAfterCap),
            cliRetryAfterCap: self::float($configuration['cliRetryAfterCap'] ?? null, $default->cliRetryAfterCap),
            maxResponseBytes: (int) self::float($configuration['maxResponseBytes'] ?? null, $default->maxResponseBytes),
        );
    }

    private static function float(mixed $value, float|int $default): float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : (float) $default;
    }
}
