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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Metadata;

use KonradMichalik\Typo3Outbound\Metadata\TypeResolver;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\TypeResolver\Subject;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * TypeResolverTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(TypeResolver::class)]
final class TypeResolverTest extends TestCase
{
    private const NS = 'KonradMichalik\\Typo3Outbound\\Tests\\Unit\\Fixtures\\TypeResolver\\';

    #[Test]
    #[DataProvider('signatures')]
    public function itQualifiesClassNamesAndLeavesTypeSyntaxAlone(string $signature, string $expected): void
    {
        $resolved = (new TypeResolver())->resolve($signature, new ReflectionClass(Subject::class));

        self::assertSame(str_replace('NS\\', self::NS, $expected), $resolved);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function signatures(): iterable
    {
        yield 'plain class' => ['PetDto', 'NS\\PetDto'];
        yield 'list of class' => ['list<PetDto>', 'list<NS\\PetDto>'];
        yield 'imported alias' => ['Alias', 'Vendor\\Other\\Dto'];
        yield 'leading backslash' => ['list<\\Foo\\Bar>', 'list<Foo\\Bar>'];
        yield 'builtin' => ['array<string, int>', 'array<string, int>'];
        yield 'hyphenated list' => ['non-empty-list<PetDto>', 'non-empty-list<NS\\PetDto>'];
        yield 'class string' => ['class-string<PetDto>', 'class-string<NS\\PetDto>'];
        yield 'integer pseudo type' => ['positive-int', 'positive-int'];
        yield 'integer range' => ['int<0, max>', 'int<0, max>'];
        yield 'integer range with min' => ['int<min, 10>', 'int<min, 10>'];
        yield 'array shape' => ['array{id: int, name: string}', 'array{id: int, name: string}'];
        yield 'array shape with optional key' => ['array{name?: string, pet?: PetDto}', 'array{name?: string, pet?: NS\\PetDto}'];
        yield 'union' => ['PetDto|null', 'NS\\PetDto|null'];
    }
}
