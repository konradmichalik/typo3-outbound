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
 * Flight.
 *
 * A cache miss in progress. Further calls for the same key in the same
 * process follow it instead of sending their own request.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Flight
{
    public ?Pending $leader = null;
    public ?Result $result = null;
    public ?\Throwable $error = null;

    public function isDone(): bool
    {
        return null !== $this->result || null !== $this->error;
    }

    public function outcome(): Result
    {
        if (null !== $this->error) {
            throw $this->error;
        }

        return $this->result ?? throw new \LogicException('Flight has no outcome yet.', 1794700151);
    }
}
