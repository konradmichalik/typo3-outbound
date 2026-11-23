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
 * CallContext.
 *
 * What the current request knows about its scope: an identifier (in TYPO3 the
 * site), the runtime settings of that scope and the language. Without a request
 * (CLI, scheduler, worker) a scope has to be selected explicitly, see ScopeRunner.
 * The scope keeps circuit breaker, rate limit and token state apart when a
 * connection points to a different API per scope.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface CallContext
{
    public function scope(): ?string;

    /**
     * @throws \KonradMichalik\Typo3Outbound\Exception\MissingContext when no scope is available
     */
    public function setting(string $key): mixed;

    /**
     * BCP 47 language tag of the current language, e.g. "de-DE".
     */
    public function language(): ?string;
}
