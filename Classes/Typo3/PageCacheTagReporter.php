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

namespace KonradMichalik\Typo3Outbound\Typo3;

use KonradMichalik\Typo3Outbound\Cache\CacheTagReporter;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Cache\{CacheDataCollectorInterface, CacheTag};

use function array_map;

/**
 * PageCacheTagReporter.
 *
 * Adds the tags of API data to the page cache via the request attribute
 * `frontend.cache.collector` (TYPO3 13.3+), which also caps the page's lifetime.
 * Flushing a tag such as `outbound_crm` therefore clears every page showing
 * that data.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PageCacheTagReporter implements CacheTagReporter
{
    public function __construct(
        private RequestContext $context,
    ) {}

    public function report(array $tags, int $lifetime): void
    {
        // In TYPO3 14.3 the core attaches the collector before typo3/cms-frontend/site, so the
        // request seen by RequestContextMiddleware carries it. The global request is a fallback
        // for code paths where the stored request is not the frontend request.
        $global = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $collector = ($global instanceof ServerRequestInterface ? $global->getAttribute('frontend.cache.collector') : null)
            ?? $this->context->request()?->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollectorInterface) {
            return;
        }
        $collector->addCacheTags(...array_map(static fn(string $tag): CacheTag => new CacheTag($tag, $lifetime), $tags));
    }
}
