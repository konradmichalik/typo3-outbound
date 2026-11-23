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

namespace KonradMichalik\Typo3Outbound;

use KonradMichalik\Outbound\Context\{CallContext, ScopeRunner};
use KonradMichalik\Outbound\Exception\MissingContext;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\{Site, SiteLanguage};
use TYPO3\CMS\Core\Site\SiteFinder;

use function sprintf;
use function str_replace;

/**
 * RequestContext.
 *
 * The call context of the current TYPO3 request, set by RequestContextMiddleware
 * in the frontend and backend. Outside a request a site is selected explicitly:
 *
 *     $context->withSite('main', fn () => $contacts->find(42));
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RequestContext implements CallContext, ScopeRunner
{
    private ?ServerRequestInterface $request = null;
    private ?Site $scopedSite = null;

    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @internal called by RequestContextMiddleware
     */
    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function request(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Runs the callback with the given site as context, e.g. in CLI or Messenger.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withSite(string $identifier, callable $callback): mixed
    {
        $previous = $this->scopedSite;
        $this->scopedSite = $this->siteFinder->getSiteByIdentifier($identifier);
        try {
            return $callback();
        } finally {
            $this->scopedSite = $previous;
        }
    }

    public function runInScope(string $scope, callable $callback): mixed
    {
        return $this->withSite($scope, $callback);
    }

    public function scope(): ?string
    {
        return $this->site()?->getIdentifier();
    }

    public function setting(string $key): mixed
    {
        $site = $this->site() ?? throw new MissingContext(sprintf('Setting "%s" needs a site. Outside a request, select one with RequestContext::withSite() or --scope.', $key), 1794700190);

        return $site->getSettings()->get($key);
    }

    public function language(): ?string
    {
        $language = $this->request?->getAttribute('language');

        return $language instanceof SiteLanguage ? str_replace('_', '-', $language->getLocale()->getName()) : null;
    }

    private function site(): ?Site
    {
        if (null !== $this->scopedSite) {
            return $this->scopedSite;
        }
        $site = $this->request?->getAttribute('site');

        return $site instanceof Site ? $site : null;
    }
}
