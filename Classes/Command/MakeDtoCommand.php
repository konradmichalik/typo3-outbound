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

use KonradMichalik\Typo3Outbound\Codegen\DtoGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MakeDtoCommand.
 *
 *     outbound:make:dto ContactDto --from=contact.json --namespace='Vendor\Site\Dto' --output=Classes/Dto --camel
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MakeDtoCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('class', InputArgument::REQUIRED, 'Name of the root DTO class')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'JSON file with a sample response')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the generated classes')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Target directory')
            ->addOption('camel', null, InputOption::VALUE_NONE, 'Convert snake_case keys to camelCase properties')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sample = json_decode((string) @file_get_contents((string) $input->getOption('from')), true);
        if (null === $sample) {
            $output->writeln('<error>--from must point to a readable JSON file.</error>');

            return Command::INVALID;
        }
        $files = (new DtoGenerator())->generate((string) $input->getOption('namespace'), (string) $input->getArgument('class'), $sample, true === $input->getOption('camel'));

        return Writer::write($files, (string) $input->getOption('output'), true === $input->getOption('force'), $output, static fn(string $class): string => $class.'.php');
    }
}
