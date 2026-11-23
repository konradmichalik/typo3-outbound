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

use KonradMichalik\Typo3Outbound\Auth\{Authenticator, TokenProvider};
use KonradMichalik\Typo3Outbound\Command\{CallCommand, EndpointLocator};
use KonradMichalik\Typo3Outbound\Context\{SapiExecutionContext, ScopeRunner, StaticCallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Http\RequestBuilder;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Resilience\InMemoryStateStore;
use KonradMichalik\Typo3Outbound\Runtime\{CallFactory, RequestBudget, Settings};
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\FakeClock;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\QueryCommandApi;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../Fixtures/CommandDefinitions.php';

/**
 * CallCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(CallCommand::class)]
final class CallCommandTest extends TestCase
{
    #[Test]
    public function anEmptyValueIsPassedAsEmptyString(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['q='], '--dry-run' => true]));
    }

    #[Test]
    public function anUnknownArgumentNameFailsInsteadOfBeingDropped(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['query=pets'], '--dry-run' => true]));
        self::assertStringContainsString('query', $tester->getDisplay());
    }

    #[Test]
    public function aRepeatedNameOrAPairWithoutValueFails(): void
    {
        self::assertSame(Command::INVALID, $this->tester()->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['q=a', 'q=b'], '--dry-run' => true]));
        self::assertSame(Command::INVALID, $this->tester()->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['q'], '--dry-run' => true]));
    }

    #[Test]
    public function aScopeWithoutAScopeRunnerIsRejected(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['q=a'], '--scope' => 'main', '--dry-run' => true]));
        self::assertStringContainsString('--scope needs a framework integration', $tester->getDisplay());
    }

    #[Test]
    public function aScopeRunsTheCallThroughTheScopeRunner(): void
    {
        $runner = new class implements ScopeRunner {
            public ?string $scope = null;

            public function runInScope(string $scope, callable $callback): mixed
            {
                $this->scope = $scope;

                return $callback();
            }
        };
        $tester = $this->tester($runner);

        self::assertSame(Command::SUCCESS, $tester->execute(['endpoint' => 'QueryCommandApi::search', 'arguments' => ['q=a'], '--scope' => 'main', '--dry-run' => true]));
        self::assertSame('main', $runner->scope);
    }

    private function tester(?ScopeRunner $scopes = null): CommandTester
    {
        $context = new StaticCallContext();
        $values = new ValueResolver($context);
        $store = new InMemoryStateStore();
        $clock = new FakeClock();
        $execution = new SapiExecutionContext(false);
        $settings = new Settings();
        $mock = new MockTransport();
        $tokens = new TokenProvider($mock, $store, $values, $context, $clock, new RequestBudget($settings, $execution, $clock));
        $calls = new CallFactory(new RequestBuilder($values, $context), new Authenticator($values, $tokens, $context), $store, $execution, $settings);
        $outbound = Outbound::create([QueryCommandApi::class])->withTransport($mock)->withClock($clock)->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'));

        return new CommandTester(new CallCommand($outbound->registry(), new EndpointLocator($outbound->registry()), $outbound->invoker(), $calls, $scopes));
    }
}
