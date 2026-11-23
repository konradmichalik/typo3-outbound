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

use KonradMichalik\Outbound\Outbound;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function count;
use function dirname;

/**
 * ExceptionCodeUniquenessTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class ExceptionCodeUniquenessTest extends TestCase
{
    #[Test]
    public function everyExceptionCodeIsUsedOnceAcrossTheExtensionAndTheCore(): void
    {
        $sites = [];
        $directories = [__DIR__.'/../../Classes', dirname((string) (new ReflectionClass(Outbound::class))->getFileName())];

        foreach ($directories as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                preg_match_all('/\b1794\d{6}\b/', (string) file_get_contents($file->getPathname()), $matches);
                foreach ($matches[0] as $code) {
                    $sites[$code][] = $file->getPathname();
                }
            }
        }

        self::assertNotEmpty($sites);
        self::assertSame([], array_filter($sites, static fn (array $paths): bool => count($paths) > 1));
    }
}
