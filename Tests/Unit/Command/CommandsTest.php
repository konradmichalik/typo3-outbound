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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Command;

use KonradMichalik\OutboundPetstore\Api\StoreApi;
use KonradMichalik\Typo3Outbound\Command\{DebugCommand, EndpointLocator, HealthCommand, LintCommand};
use KonradMichalik\Typo3Outbound\Resilience\InMemoryStateStore;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, PetstoreKernel};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * CommandsTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(DebugCommand::class)]
#[CoversClass(LintCommand::class)]
#[CoversClass(HealthCommand::class)]
final class CommandsTest extends TestCase
{
    #[Test]
    public function debugListsEndpointsWithOrigins(): void
    {
        $registry = PetstoreKernel::create(new MockTransport(), new FakeClock())->registry();
        $tester = new CommandTester(new DebugCommand($registry, new EndpointLocator($registry)));

        $tester->execute(['--format' => 'json']);
        $output = json_decode($tester->getDisplay(), true);

        self::assertSame(1, $output['schemaVersion']);
        $find = array_values(array_filter($output['endpoints'], static fn(array $row): bool => 'PetApi::find' === $row['endpoint']))[0];
        self::assertSame('/pet/{petId}', $find['path']);
        self::assertStringContainsString('PetstoreApi', $find['retry']);
        self::assertStringContainsString('inactive', array_values(array_filter($output['endpoints'], static fn(array $row): bool => 'PetApi::add' === $row['endpoint']))[0]['retry']);
    }

    #[Test]
    public function lintWarnsAboutRetriesWithoutEffect(): void
    {
        $registry = PetstoreKernel::create(new MockTransport(), new FakeClock())->registry();
        $tester = new CommandTester(new LintCommand($registry, new EndpointLocator($registry)));

        self::assertSame(1, $tester->execute(['--strict' => true]));
        self::assertStringContainsString('PetApi::add: #[Retry] has no effect', $tester->getDisplay());
    }

    #[Test]
    public function healthRunsChecksAndReportsDeprecations(): void
    {
        $mock = new MockTransport();
        $mock->on(StoreApi::class, 'inventory')->respondWithJson(['available' => 1], 200, ['deprecation' => '@1767225600']);
        $store = new InMemoryStateStore();
        $outbound = PetstoreKernel::create($mock, new FakeClock())->withStateStore($store);
        $outbound->api(StoreApi::class)->inventory();
        $tester = new CommandTester(new HealthCommand(new EndpointLocator($outbound->registry()), $outbound->invoker(), $store));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('ok     StoreApi::inventory', $tester->getDisplay());
        self::assertStringContainsString('deprecated StoreApi::inventory', $tester->getDisplay());

        $mock->reset();
        $mock->on(StoreApi::class, 'inventory')->respondWith(500);
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('failed StoreApi::inventory', $tester->getDisplay());
    }
}
