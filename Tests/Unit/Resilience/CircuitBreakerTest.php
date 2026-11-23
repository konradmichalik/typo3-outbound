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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Resilience;

use KonradMichalik\Typo3Outbound\Attribute\{CircuitBreaker as CircuitBreakerSettings, Connection};
use KonradMichalik\Typo3Outbound\Exception\ApiUnavailable;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Resilience\{CircuitBreaker, InMemoryStateStore};
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, SwitchableCallContext};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\MyCRMApi;
use KonradMichalik\Typo3Outbound\Value\SiteSetting;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * CircuitBreakerTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CircuitBreaker::class)]
final class CircuitBreakerTest extends TestCase
{
    private FakeClock $clock;
    private CircuitBreaker $breaker;
    private SwitchableCallContext $context;

    protected function setUp(): void
    {
        $this->clock = new FakeClock();
        $this->context = new SwitchableCallContext('site_a');
        $this->breaker = new CircuitBreaker(new InMemoryStateStore(), $this->clock, new NullLogger(), $this->context);
    }

    #[Test]
    public function itOpensExactlyAtTheThreshold(): void
    {
        $crm = $this->connection('crm');

        $this->recordFailures($crm, 2);
        $this->breaker->guard($crm, 'E');
        $this->recordFailures($crm, 1);

        $this->expectException(ApiUnavailable::class);
        $this->breaker->guard($crm, 'E');
    }

    #[Test]
    public function itLetsCallsThroughAgainOnceTheCooldownIsOver(): void
    {
        $crm = $this->connection('crm');
        $this->recordFailures($crm, 3);

        $this->clock->advance(29);
        $this->assertBlocked($crm);

        $this->clock->advance(2);
        $this->breaker->guard($crm, 'E');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function aSuccessInBetweenResetsTheFailureCount(): void
    {
        $crm = $this->connection('crm');
        $this->recordFailures($crm, 2);
        $this->breaker->guard($crm, 'E');
        $this->breaker->success($crm);

        $this->recordFailures($crm, 2);

        $this->breaker->guard($crm, 'E');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function connectionsDoNotAffectEachOther(): void
    {
        $this->recordFailures($this->connection('crm'), 3);

        $this->assertBlocked($this->connection('crm'));
        $this->breaker->guard($this->connection('shop'), 'E');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function aConnectionWithASiteSpecificBaseUriTripsOnlyForItsSite(): void
    {
        $perSite = $this->connection('crm', new Connection(new SiteSetting('crm.baseUri'), allowedHosts: ['crm.example.com']));
        $this->recordFailures($perSite, 3);

        $this->assertBlocked($perSite);
        $this->context->site = 'site_b';
        $this->breaker->guard($perSite, 'E');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function aConnectionWithOneBaseUriIsSharedByAllSites(): void
    {
        $shared = $this->connection('crm');
        $this->recordFailures($shared, 3);

        $this->context->site = 'site_b';

        $this->assertBlocked($shared);
    }

    #[Test]
    public function aConnectionWithoutBreakerIsNeverBlocked(): void
    {
        $plain = new ConnectionMetadata('plain', MyCRMApi::class, new Connection('https://x'));

        for ($i = 0; $i < 20; ++$i) {
            $this->breaker->failure($plain);
        }

        $this->breaker->guard($plain, 'E');
        $this->addToAssertionCount(1);
    }

    private function connection(string $name, ?Connection $definition = null): ConnectionMetadata
    {
        return new ConnectionMetadata($name, MyCRMApi::class, $definition ?? new Connection('https://x'), circuitBreaker: new CircuitBreakerSettings(failureThreshold: 3, cooldown: 30));
    }

    private function recordFailures(ConnectionMetadata $connection, int $times): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $this->breaker->failure($connection);
        }
    }

    private function assertBlocked(ConnectionMetadata $connection): void
    {
        try {
            $this->breaker->guard($connection, 'E');
            self::fail('Expected the circuit to be open.');
        } catch (ApiUnavailable) {
            $this->addToAssertionCount(1);
        }
    }
}
