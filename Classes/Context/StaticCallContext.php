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
 * A fixed context, used in tests and outside a framework.
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
        private ?string $scope = null,
        private array $settings = [],
        private ?string $language = null,
    ) {}

    public function scope(): ?string
    {
        return $this->scope;
    }

    public function setting(string $key): mixed
    {
        if (null === $this->scope) {
            throw new MissingContext(sprintf('Setting "%s" needs a scope. Select one explicitly outside a web request.', $key), 1794700050);
        }

        return array_key_exists($key, $this->settings) ? $this->settings[$key] : null;
    }

    public function language(): ?string
    {
        return $this->language;
    }
}
