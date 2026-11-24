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

use KonradMichalik\Typo3Outbound\Context\Typo3ExecutionContext;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, PreserveGlobalState, RunTestsInSeparateProcesses, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};

/**
 * Typo3ExecutionContextTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Typo3ExecutionContext::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Typo3ExecutionContextTest extends TestCase
{
    #[Test]
    #[DataProvider('applicationContexts')]
    public function webAndDevelopmentFollowTheTypo3Environment(string $context, bool $cli, bool $web, bool $development): void
    {
        $this->initializeEnvironment($context, $cli);

        $execution = new Typo3ExecutionContext();

        self::assertSame($web, $execution->isWeb());
        self::assertSame($development, $execution->isDevelopment());
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function applicationContexts(): iterable
    {
        yield 'production on the web' => ['Production', false, true, false];
        yield 'development on the web' => ['Development', false, true, true];
        yield 'production on the command line' => ['Production', true, false, false];
        yield 'a development sub context' => ['Development/Docker', true, false, true];
    }

    private function initializeEnvironment(string $context, bool $cli): void
    {
        Environment::initialize(new ApplicationContext($context), $cli, true, '/tmp/project', '/tmp/project/public', '/tmp/project/var', '/tmp/project/config', '/tmp/project/public/index.php', 'UNIX');
    }
}
