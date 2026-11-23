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

use KonradMichalik\Typo3Outbound\LockFactoryCacheLock;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Locking\{LockFactory, LockingStrategyInterface};

/**
 * LockFactoryCacheLockTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(LockFactoryCacheLock::class)]
final class LockFactoryCacheLockTest extends TestCase
{
    #[Test]
    public function releasingTheHandleAlsoDestroysTheLockResource(): void
    {
        $strategy = new RecordingStrategy();
        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLocker')->willReturn($strategy);

        $handle = (new LockFactoryCacheLock($factory, new FakeClock()))->acquire('some-key', 0.0);
        self::assertNotNull($handle);
        self::assertSame(['acquire'], $strategy->calls);

        $handle->release();

        self::assertSame(['acquire', 'release', 'destroy'], $strategy->calls);
    }

    #[Test]
    public function aLockThatCouldNotBeTakenIsLeftAlone(): void
    {
        $strategy = new RecordingStrategy(acquirable: false);
        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLocker')->willReturn($strategy);

        self::assertNull((new LockFactoryCacheLock($factory, new FakeClock()))->acquire('some-key', 0.0));
        self::assertNotContains('destroy', $strategy->calls);
    }
}

/**
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RecordingStrategy implements LockingStrategyInterface
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param string $subject
     */
    public function __construct(
        $subject = 'test',
        private readonly bool $acquirable = true,
    ) {}

    public static function getCapabilities(): int
    {
        return self::LOCK_CAPABILITY_EXCLUSIVE | self::LOCK_CAPABILITY_NOBLOCK;
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public function acquire($mode = self::LOCK_CAPABILITY_EXCLUSIVE): bool
    {
        if ($this->acquirable) {
            $this->calls[] = 'acquire';
        }

        return $this->acquirable;
    }

    public function release(): bool
    {
        $this->calls[] = 'release';

        return true;
    }

    public function destroy(): void
    {
        $this->calls[] = 'destroy';
    }

    public function isAcquired(): bool
    {
        return true;
    }
}
