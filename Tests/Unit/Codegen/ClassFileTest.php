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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Codegen;

use KonradMichalik\Typo3Outbound\Codegen\ClassFile;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * ClassFileTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ClassFile::class)]
final class ClassFileTest extends TestCase
{
    #[Test]
    public function aCommentTerminatorIsDefused(): void
    {
        self::assertStringNotContainsString('*/', ClassFile::docText("x */ system('y'); /*"));
    }

    #[Test]
    public function controlCharactersGoButTabsAndNewlinesStay(): void
    {
        self::assertSame("a\tb\nc", ClassFile::docText("a\tb\n\x07c"));
    }

    #[Test]
    public function textThatIsNotControlStaysIntact(): void
    {
        self::assertSame("caf\u{e9} \u{1F468}\u{200D}\u{1F469}", ClassFile::docText("caf\u{e9} \u{1F468}\u{200D}\u{1F469}"));
    }

    #[Test]
    public function bidirectionalOverridesAreRemoved(): void
    {
        self::assertSame('ab', ClassFile::docText("a\u{202E}b"));
    }

    #[Test]
    public function invalidUtf8DoesNotBlankTheWholeText(): void
    {
        $text = ClassFile::docText("List pets \xff for a shop");

        self::assertStringContainsString('List pets', $text);
        self::assertStringContainsString('for a shop', $text);
    }

    #[Test]
    public function docLineCollapsesToOneLine(): void
    {
        self::assertSame('one two three', ClassFile::docLine("one\n two\t three"));
    }
}
