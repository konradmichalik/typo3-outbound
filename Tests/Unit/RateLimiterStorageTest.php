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

use KonradMichalik\Typo3Outbound\Resilience\RateLimiterStorage;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\{Rate, SlidingWindow, TokenBucket, Window};
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\{FrontendInterface, VariableFrontend};

use function hash;
use function serialize;

/**
 * RateLimiterStorageTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RateLimiterStorage::class)]
final class RateLimiterStorageTest extends TestCase
{
    #[Test]
    public function everyPolicyStateTheLimiterFactoryCanProduceSurvivesTheRoundTrip(): void
    {
        $storage = new RateLimiterStorage($this->frontend());
        $states = [
            new Window('window', 60, 10),
            new SlidingWindow('sliding', 60),
            new TokenBucket('bucket', 5, Rate::perMinute(5)),
        ];

        foreach ($states as $state) {
            $storage->save($state);
            self::assertEquals($state, $storage->fetch($state->getId()));
        }
    }

    #[Test]
    public function anUnknownIdIsAMiss(): void
    {
        self::assertNull((new RateLimiterStorage($this->frontend()))->fetch('missing'));
    }

    #[Test]
    public function aDeletedStateIsGone(): void
    {
        $storage = new RateLimiterStorage($this->frontend());
        $storage->save(new Window('petstore', 60, 10));

        $storage->delete('petstore');

        self::assertNull($storage->fetch('petstore'));
    }

    #[Test]
    public function aPayloadOfAnotherClassIsNeverInstantiated(): void
    {
        WakeupProbe::$woken = false;
        $frontend = $this->frontend();
        $frontend->set('limiter_'.hash('xxh128', 'evil'), serialize(new WakeupProbe()));

        self::assertNull((new RateLimiterStorage($frontend))->fetch('evil'));
        self::assertFalse(WakeupProbe::$woken, 'unserialize() must not run code of classes outside the allow list.');
    }

    #[Test]
    public function aPayloadThatIsNotAStringIsAMiss(): void
    {
        $frontend = $this->frontend();
        $frontend->set('limiter_'.hash('xxh128', 'broken'), ['array']);

        self::assertNull((new RateLimiterStorage($frontend))->fetch('broken'));
    }

    #[Test]
    public function theStateExpiresWhenTheLimiterWindowEnds(): void
    {
        $state = new Window('petstore', 60, 10, 1_800_000_000.0);
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('set')->with('limiter_'.hash('xxh128', 'petstore'), serialize($state), [], $state->getExpirationTime());

        (new RateLimiterStorage($cache))->save($state);
    }

    private function frontend(): VariableFrontend
    {
        return new VariableFrontend('outbound_state', new TransientMemoryBackend([]));
    }
}

/**
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class WakeupProbe
{
    public static bool $woken = false;

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        self::$woken = true;
    }
}
