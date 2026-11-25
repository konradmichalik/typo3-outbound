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

use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Typo3Outbound\Context\Typo3ExecutionContext;
use PHPUnit\Framework\Attributes\{CoversClass, Test, TestWith};
use PHPUnit\Framework\TestCase;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(Typo3ExecutionContext::class)]
final class Typo3ExecutionContextTest extends TestCase
{
    #[Test]
    #[TestWith([new WithEnvironment(context: 'Production', cli: false), true, false], 'production on the web')]
    #[TestWith([new WithEnvironment(context: 'Development', cli: false), true, true], 'development on the web')]
    #[TestWith([new WithEnvironment(context: 'Production', cli: true), false, false], 'production on the command line')]
    #[TestWith([new WithEnvironment(context: 'Development/Docker', cli: true), false, true], 'a development sub context')]
    public function webAndDevelopmentFollowTheTypo3Environment(WithEnvironment $environment, bool $web, bool $development): void
    {
        $execution = new Typo3ExecutionContext();

        self::assertSame($web, $execution->isWeb());
        self::assertSame($development, $execution->isDevelopment());
    }
}
