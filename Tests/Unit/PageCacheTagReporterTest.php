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

namespace KonradMichalik\Typo3Outbound\Tests\Unit;

use KonradMichalik\Typo3Outbound\Cache\PageCacheTagReporter;
use KonradMichalik\Typo3Outbound\Context\RequestContext;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TYPO3\CMS\Core\Cache\{CacheDataCollector, CacheTag};
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * PageCacheTagReporterTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(PageCacheTagReporter::class)]
final class PageCacheTagReporterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
    }

    #[Test]
    public function tagsAndLifetimeReachTheFrontendCacheCollector(): void
    {
        $collector = new CacheDataCollector();
        $context = new RequestContext((new ReflectionClass(SiteFinder::class))->newInstanceWithoutConstructor());
        $context->setRequest((new ServerRequest('https://localhost/', 'GET', 'php://memory'))->withAttribute('frontend.cache.collector', $collector));

        (new PageCacheTagReporter($context))->report(['outbound_petstore', 'petstore_pet_10'], 60);

        self::assertEquals([new CacheTag('outbound_petstore', 60), new CacheTag('petstore_pet_10', 60)], array_values($collector->getCacheTags()));
        self::assertLessThanOrEqual(60, $collector->resolveLifetime());
    }

    #[Test]
    public function aCollectorAddedLaterIsFoundThroughTheGlobalRequest(): void
    {
        $collector = new CacheDataCollector();
        $context = new RequestContext((new ReflectionClass(SiteFinder::class))->newInstanceWithoutConstructor());
        $context->setRequest(new ServerRequest('https://localhost/', 'GET', 'php://memory'));
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://localhost/', 'GET', 'php://memory'))->withAttribute('frontend.cache.collector', $collector);

        (new PageCacheTagReporter($context))->report(['outbound_petstore'], 30);

        self::assertCount(1, $collector->getCacheTags());
    }
}
