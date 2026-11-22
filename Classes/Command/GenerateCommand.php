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

use KonradMichalik\Typo3Outbound\Codegen\OpenApiGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

use function file_get_contents;
use function is_array;
use function json_decode;
use function str_ends_with;

/**
 * GenerateCommand.
 *
 *     outbound:generate --from=openapi.yaml --name=Petstore --namespace='Vendor\Site\Petstore' --output=Classes/Petstore
 *
 * The counterpart of routing:openapi: where routing exports its own endpoints,
 * this imports a foreign API.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'OpenAPI 3 document, JSON or YAML')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of the API, e.g. Petstore (gives PetstoreApi and connection "petstore")')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Root namespace; interfaces go to \\Api, DTOs to \\Dto')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Target directory')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $from = (string) $input->getOption('from');
        try {
            $spec = str_ends_with($from, '.json') ? json_decode((string) file_get_contents($from), true) : Yaml::parseFile($from);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Cannot read '.$from.': '.$exception->getMessage().'</error>');

            return Command::INVALID;
        }
        if (!is_array($spec)) {
            $output->writeln('<error>'.$from.' is not an OpenAPI document.</error>');

            return Command::INVALID;
        }
        /** @var array<string, mixed> $spec */
        $files = (new OpenApiGenerator())->generate($spec, (string) $input->getOption('namespace'), (string) $input->getOption('name'));

        return Writer::write($files, (string) $input->getOption('output'), true === $input->getOption('force'), $output, static fn(string $path): string => $path);
    }
}
