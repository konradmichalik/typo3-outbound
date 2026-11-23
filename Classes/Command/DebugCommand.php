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

use KonradMichalik\Typo3Outbound\Metadata\{EndpointMetadata, Registry};
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * DebugCommand.
 *
 * Lists connections and endpoints with their effective configuration and where
 * each setting comes from.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class DebugCommand extends Command
{
    public function __construct(
        private readonly Registry $registry,
        private readonly EndpointLocator $endpoints,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Lists every connection and endpoint with the settings that apply to it: HTTP method,
            path, return type, retry, timeout and cache. Behind each setting it names where it comes
            from: the method, the resource interface or the connection.

              <info>%command.full_name%</info>
              <info>%command.full_name% petstore</info>            only the endpoints of one connection
              <info>%command.full_name% --format=json</info>       machine-readable, e.g. for checks in CI
            HELP);
        $this->addArgument('connection', InputArgument::OPTIONAL, 'Only this connection')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $input->getArgument('connection');
        $rows = [];
        foreach ($this->endpoints->all(is_string($connection) ? $connection : null) as $endpoint) {
            $rows[] = $this->row($endpoint);
        }

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode(['schemaVersion' => 1, 'endpoints' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        foreach ($this->registry->connectionNames() as $name) {
            if (is_string($connection) && $connection !== $name) {
                continue;
            }
            $metadata = $this->registry->connection($name);
            $baseUri = $metadata->definition->baseUri;
            $output->writeln(sprintf('<info>%s</info> %s, base URI %s, auth %s, format %s', $name, $metadata->interface, is_string($baseUri) ? $baseUri : $baseUri->describe(), null !== $metadata->auth ? (new ReflectionClass($metadata->auth))->getShortName() : 'none', (new ReflectionClass($metadata->definition->format))->getShortName()));
        }
        $table = new Table($output);
        $table->setHeaders(['Endpoint', 'Method', 'Path', 'Returns', 'Cache', 'Retry', 'Timeout']);
        foreach ($rows as $row) {
            $table->addRow([$row['endpoint'], $row['method'], $row['path'], $row['returns'], $row['cache'], $row['retry'], $row['timeout']]);
        }
        $table->render();

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function row(EndpointMetadata $endpoint): array
    {
        $origin = static fn (string $name): string => isset($endpoint->origins[$name]) ? ' ('.$endpoint->origins[$name].')' : '';

        return [
            'endpoint' => $endpoint->key,
            'connection' => $endpoint->connection,
            'method' => $endpoint->httpMethod(),
            'path' => '' === $endpoint->path ? '/' : $endpoint->path,
            'returns' => $endpoint->returnType->kind->value.' '.$endpoint->returnType->signature,
            'cache' => null !== $endpoint->cache ? $endpoint->cache->lifetime.'s'.$origin('Cache') : '-',
            'retry' => null !== $endpoint->retry ? ($endpoint->idempotent || $endpoint->retry->force ? $endpoint->retry->maxRetries.'x' : 'inactive (not idempotent)').$origin('Retry') : '-',
            'timeout' => null !== $endpoint->timeout ? $endpoint->timeout->seconds.'s'.$origin('Timeout') : 'default',
        ];
    }
}
