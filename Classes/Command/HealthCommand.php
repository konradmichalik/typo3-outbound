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

use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use KonradMichalik\Typo3Outbound\Runtime\{ConventionObserver, EndpointInvoker};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Traversable;

use function is_string;
use function iterator_to_array;
use function microtime;
use function sprintf;

/**
 * HealthCommand.
 *
 * Calls every #[HealthCheck] endpoint and reports deprecations the APIs
 * announced via Sunset or Deprecation headers. Exit code 1 on any failure, for
 * monitoring after a deployment.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class HealthCommand extends Command
{
    public function __construct(
        private readonly EndpointLocator $endpoints,
        private readonly EndpointInvoker $invoker,
        private readonly StateStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Calls every endpoint marked with #[HealthCheck], without arguments, and reports
            deprecations the APIs announced through Deprecation and Sunset headers on earlier calls.

              <info>%command.full_name%</info>

            Exit code 1 if any health check fails, so it fits into monitoring after a deployment.
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = 0;
        $checks = 0;
        foreach ($this->endpoints->all() as $endpoint) {
            $deprecation = $this->store->get(ConventionObserver::deprecationKey($endpoint->key));
            if (null !== $deprecation) {
                $output->writeln(sprintf('<comment>deprecated</comment> %s (Deprecation: %s, Sunset: %s)', $endpoint->key, is_string($deprecation['deprecation'] ?? null) ? $deprecation['deprecation'] : '-', is_string($deprecation['sunset'] ?? null) ? $deprecation['sunset'] : '-'));
            }
            if (!$endpoint->healthCheck) {
                continue;
            }
            ++$checks;
            $started = microtime(true);
            try {
                $result = $this->invoker->call($endpoint->interface, $endpoint->method, []);
                if ($result instanceof Traversable) {
                    iterator_to_array($result, false);
                }
                $output->writeln(sprintf('<info>ok</info>     %s (%d ms)', $endpoint->key, (microtime(true) - $started) * 1000));
            } catch (Throwable $exception) {
                ++$failed;
                $output->writeln(sprintf('<error>failed</error> %s: %s', $endpoint->key, $exception->getMessage()));
            }
        }
        $output->writeln(sprintf('%d health checks, %d failed.', $checks, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
