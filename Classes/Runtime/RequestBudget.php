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

use KonradMichalik\Typo3Outbound\Context\ExecutionContext;
use KonradMichalik\Typo3Outbound\Support\Clock;

use function max;
use function min;

use const INF;

/**
 * RequestBudget.
 *
 * The time all outbound calls of one web request may take together. In CLI the
 * budget is unlimited and only the per-call timeout applies.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RequestBudget
{
    private ?float $startedAt = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly ExecutionContext $context,
        private readonly Clock $clock,
    ) {}

    public function remaining(): float
    {
        if (!$this->context->isWeb()) {
            return INF;
        }
        $this->startedAt ??= $this->clock->now();

        return max(0.0, $this->settings->webBudget - ($this->clock->now() - $this->startedAt));
    }

    /**
     * Duration for the next attempt: the configured timeout, capped by the budget.
     */
    public function attemptDuration(?float $timeout): float
    {
        $default = $this->context->isWeb() ? $this->settings->webTimeout : $this->settings->cliTimeout;

        return min($timeout ?? $default, $this->remaining());
    }

    /**
     * How long a cache miss may wait for another process refreshing the same entry.
     */
    public function lockWait(): float
    {
        return min($this->context->isWeb() ? 2.0 : $this->settings->cliTimeout, $this->remaining());
    }

    public function retryAfterCap(): float
    {
        return $this->context->isWeb() ? $this->settings->webRetryAfterCap : $this->settings->cliRetryAfterCap;
    }
}
