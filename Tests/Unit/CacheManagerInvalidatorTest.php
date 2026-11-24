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

use KonradMichalik\Typo3Outbound\Cache\CacheManagerInvalidator;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * CacheManagerInvalidatorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CacheManagerInvalidator::class)]
final class CacheManagerInvalidatorTest extends TestCase
{
    #[Test]
    public function tagsAreFlushedAcrossAllCaches(): void
    {
        $manager = $this->createMock(CacheManager::class);
        $manager->expects(self::once())->method('flushCachesByTags')->with(['outbound_petstore', 'petstore_pet_10']);

        (new CacheManagerInvalidator($manager))->invalidate(['outbound_petstore', 'petstore_pet_10']);
    }

    #[Test]
    public function noTagsMeansNothingIsFlushed(): void
    {
        $manager = $this->createMock(CacheManager::class);
        $manager->expects(self::never())->method('flushCachesByTags');

        (new CacheManagerInvalidator($manager))->invalidate([]);
    }
}
