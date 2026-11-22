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

use KonradMichalik\Typo3Outbound\Exception\MissingContext;

use function array_key_exists;
use function sprintf;

/**
 * StaticCallContext.
 *
 * A fixed context, used in tests and outside TYPO3.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class StaticCallContext implements CallContext
{
    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(
        private ?string $site = null,
        private array $settings = [],
        private ?string $language = null,
    ) {}

    public function siteIdentifier(): ?string
    {
        return $this->site;
    }

    public function setting(string $key): mixed
    {
        if (null === $this->site) {
            throw new MissingContext(sprintf('Site setting "%s" needs a site. Select one explicitly outside a web request.', $key), 1794700050);
        }

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : null;
    }

    public function language(): ?string
    {
        return $this->language;
    }
}
