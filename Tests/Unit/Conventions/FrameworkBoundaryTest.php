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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Conventions;

use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function dirname;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * FrameworkBoundaryTest.
 *
 * Everything outside Classes/Typo3 and Classes/Middleware has to stay free of
 * TYPO3, so it can move into a framework-agnostic core unchanged.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class FrameworkBoundaryTest extends TestCase
{
    private const INTEGRATION_DIRECTORIES = ['Typo3/', 'Middleware/'];

    #[Test]
    public function coreClassesDoNotDependOnTypo3(): void
    {
        $root = dirname(__DIR__, 3).'/Classes/';
        $violations = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root));
            if ('php' !== $file->getExtension() || $this->isIntegration($path)) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (str_contains($source, 'TYPO3\\') || str_contains($source, 'Typo3Outbound\\Typo3\\') || str_contains($source, 'Typo3Outbound\\Middleware\\')) {
                $violations[] = $path;
            }
        }

        self::assertSame([], $violations, 'These files reference TYPO3 or the integration namespaces.');
    }

    private function isIntegration(string $path): bool
    {
        foreach (self::INTEGRATION_DIRECTORIES as $directory) {
            if (str_starts_with($path, $directory)) {
                return true;
            }
        }

        return false;
    }
}
