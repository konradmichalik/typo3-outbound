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

namespace KonradMichalik\Typo3Outbound\Transport;

/**
 * TransportOptions.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class TransportOptions
{
    public function __construct(
        public float $maxDuration,
        public int $maxResponseBytes,
        public bool $verifyTls = true,
        public bool $allowPrivateNetwork = false,
    ) {}

    public function withMaxDuration(float $seconds): self
    {
        return new self($seconds, $this->maxResponseBytes, $this->verifyTls, $this->allowPrivateNetwork);
    }
}
