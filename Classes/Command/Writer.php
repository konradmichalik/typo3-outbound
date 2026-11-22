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

namespace KonradMichalik\Typo3Outbound\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function rtrim;
use function sprintf;

/**
 * Writer.
 *
 * Writes generated files without overwriting existing ones unless forced.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Writer
{
    /**
     * @param array<string, string>    $files
     * @param \Closure(string): string  $path
     */
    public static function write(array $files, string $directory, bool $force, OutputInterface $output, \Closure $path): int
    {
        if ('' === $directory) {
            $output->writeln('<error>--output is required.</error>');

            return Command::INVALID;
        }
        $skipped = 0;
        foreach ($files as $name => $code) {
            $target = rtrim($directory, '/').'/'.$path($name);
            if (is_file($target) && !$force) {
                $output->writeln(sprintf('<comment>skipped</comment> %s (exists, use --force)', $target));
                ++$skipped;
                continue;
            }
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0o775, true);
            }
            file_put_contents($target, $code);
            $output->writeln(sprintf('<info>written</info> %s', $target));
        }
        $output->writeln('Review the generated code before committing it.');

        return 0 === $skipped ? Command::SUCCESS : Command::FAILURE;
    }
}
