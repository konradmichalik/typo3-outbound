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

namespace KonradMichalik\Typo3Outbound\Context;

/**
 * ScopeRunner.
 *
 * Runs a callback with an explicitly selected scope as call context, for
 * processes without a request such as the console, a scheduler or a worker.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ScopeRunner
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runInScope(string $scope, callable $callback): mixed;
}
