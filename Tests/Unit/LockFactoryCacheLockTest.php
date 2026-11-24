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

use KonradMichalik\Typo3Outbound\Cache\LockFactoryCacheLock;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Locking\Exception\LockAcquireWouldBlockException;
use TYPO3\CMS\Core\Locking\{LockFactory, LockingStrategyInterface};

use function array_sum;
use function hash;

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
        self::assertFalse($handle->waited);
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

        $clock = new FakeClock();

        self::assertNull((new LockFactoryCacheLock($factory, $clock))->acquire('some-key', 0.0));
        self::assertNotContains('destroy', $strategy->calls);
        self::assertSame([], $clock->sleeps, 'A timeout of zero means a single attempt without waiting.');
    }

    #[Test]
    #[DataProvider('busyBehaviours')]
    public function aBusyLockIsPolledUntilItIsFreeAndTheHandleSaysSo(bool $throwWhenBusy): void
    {
        $strategy = new RecordingStrategy(busyAttempts: 2, throwWhenBusy: $throwWhenBusy);
        $clock = new FakeClock();

        $handle = (new LockFactoryCacheLock($this->factoryFor($strategy), $clock))->acquire('some-key', 1.0);

        self::assertNotNull($handle);
        self::assertTrue($handle->waited);
        self::assertSame([0.05, 0.05], $clock->sleeps);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function busyBehaviours(): iterable
    {
        yield 'acquire() returns false' => [false];
        yield 'acquire() throws LockAcquireWouldBlockException' => [true];
    }

    #[Test]
    public function aLockThatStaysBusyGivesUpAfterTheTimeout(): void
    {
        $clock = new FakeClock();

        $handle = (new LockFactoryCacheLock($this->factoryFor(new RecordingStrategy(acquirable: false)), $clock))->acquire('some-key', 0.2);

        self::assertNull($handle);
        $waited = array_sum($clock->sleeps);
        self::assertGreaterThanOrEqual(0.2 - 0.0001, $waited, 'It must wait for the whole timeout.');
        self::assertLessThanOrEqual(0.2 + 0.05 + 0.0001, $waited, 'It must stop within one poll interval of the timeout.');
    }

    #[Test]
    public function theLockNameIsDerivedFromTheKeyWithoutExposingIt(): void
    {
        $factory = $this->createMock(LockFactory::class);
        $factory->expects(self::once())->method('createLocker')
            ->with('outbound_'.hash('xxh128', 'GET https://example.com/?a=1'), LockingStrategyInterface::LOCK_CAPABILITY_EXCLUSIVE | LockingStrategyInterface::LOCK_CAPABILITY_NOBLOCK)
            ->willReturn(new RecordingStrategy());

        (new LockFactoryCacheLock($factory, new FakeClock()))->acquire('GET https://example.com/?a=1', 0.0);
    }

    private function factoryFor(RecordingStrategy $strategy): LockFactory
    {
        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLocker')->willReturn($strategy);

        return $factory;
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
        private int $busyAttempts = 0,
        private readonly bool $throwWhenBusy = false,
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
        if ($this->busyAttempts > 0) {
            --$this->busyAttempts;

            return $this->throwWhenBusy ? throw new LockAcquireWouldBlockException('busy', 1700000001) : false;
        }

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
