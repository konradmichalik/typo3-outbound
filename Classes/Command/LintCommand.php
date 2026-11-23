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

use KonradMichalik\Typo3Outbound\Metadata\Registry;
use KonradMichalik\Typo3Outbound\Value\ValueSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function getenv;
use function sprintf;
use function str_starts_with;

/**
 * LintCommand.
 *
 * Audits declarations for mistakes the container build accepts but that are
 * probably unintended. Invalid declarations never get this far; they already
 * fail the build.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LintCommand extends Command
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
            Reports declarations the container build accepts but that are probably unintended:
            a base URI from an environment variable or setting without allowedHosts, an HTTPS
            proxy (the private network block cannot see behind it), verifyTls: false, an
            #[InvalidatesCache] tag no #[Cache] sets, #[Retry] on an endpoint that is not
            idempotent, and a high maxPages. Invalid declarations never get here, they fail the build.

              <info>%command.full_name%</info>
              <info>%command.full_name% --strict</info>            exit code 1 on any warning, for CI
            HELP);
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Fail on warnings');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $warnings = [];
        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
        foreach ($this->registry->connectionNames() as $name) {
            $definition = $this->registry->connection($name)->definition;
            if ($definition->baseUri instanceof ValueSource && [] === $definition->allowedHosts) {
                $warnings[] = sprintf('Connection %s: the base URI comes from %s without allowedHosts. Whoever controls that value decides where calls and credentials go.', $name, $definition->baseUri->describe());
            }
            if (false !== $proxy && '' !== $proxy && !$definition->allowPrivateNetwork) {
                $warnings[] = sprintf('Connection %s: an HTTPS proxy is configured. NoPrivateNetworkHttpClient cannot verify the target address behind a proxy.', $name);
            }
            if (!$definition->verifyTls) {
                $warnings[] = sprintf('Connection %s: verifyTls is false. This only takes effect in the Development context.', $name);
            }
        }
        $cachedTags = [];
        foreach ($this->endpoints->all() as $endpoint) {
            foreach ($endpoint->cache->tags ?? [] as $template) {
                $cachedTags[$template] = true;
            }
        }
        foreach ($this->endpoints->all() as $endpoint) {
            foreach ($endpoint->invalidates->tags ?? [] as $template) {
                if (!isset($cachedTags[$template]) && !str_starts_with($template, 'outbound_')) {
                    $warnings[] = sprintf('%s: #[InvalidatesCache] tag "%s" is not set by any #[Cache], so flushing it clears nothing.', $endpoint->key, $template);
                }
            }
            if (null !== $endpoint->retry && !$endpoint->idempotent && !$endpoint->retry->force) {
                $warnings[] = sprintf('%s: #[Retry] has no effect, the endpoint is not idempotent. Use idempotencyKey: true or force: true if repeating is safe.', $endpoint->key);
            }
            if (null !== $endpoint->paginate && $endpoint->paginate->maxPages > 50) {
                $warnings[] = sprintf('%s: maxPages %d can block a web request for long.', $endpoint->key, $endpoint->paginate->maxPages);
            }
        }

        foreach ($warnings as $warning) {
            $output->writeln('<comment>warning</comment> '.$warning);
        }
        $output->writeln(sprintf('%d connections, %d endpoints, %d warnings.', count($this->registry->connectionNames()), count($this->endpoints->all()), count($warnings)));

        return [] !== $warnings && true === $input->getOption('strict') ? Command::FAILURE : Command::SUCCESS;
    }
}
