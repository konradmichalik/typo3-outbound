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
 * What the current request knows about the site: its identifier, its settings
 * and its language. Without a request (CLI, Scheduler, Messenger) a site has to
 * be selected explicitly, see SiteScope.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface CallContext
{
    public function siteIdentifier(): ?string;

    /**
     * @throws \KonradMichalik\Typo3Outbound\Exception\MissingContext when no site is available
     */
    public function setting(string $key): mixed;

    /**
     * BCP 47 language tag of the current site language, e.g. "de-DE".
     */
    public function language(): ?string;
}
