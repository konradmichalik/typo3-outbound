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

use KonradMichalik\Typo3Outbound\Cache\{CacheInvalidator, CacheTags};
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;
use function sprintf;

/**
 * CacheFlushCommand.
 *
 * Flushes the tag `outbound_<connection>` in all caches: cached responses and
 * every page that rendered data of the connection. Equivalent to
 * `cache:flushtags outbound_<connection>` on TYPO3 14.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheFlushCommand extends Command
{
    public function __construct(
        private readonly Registry $registry,
        private readonly CacheInvalidator $invalidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Flushes the cache tag outbound_<connection> in all caches: the cached responses of the
            connection and every page that rendered its data. On TYPO3 14 this equals
            <info>cache:flushtags outbound_<connection></info>.

              <info>%command.full_name% petstore</info>

            Connection names are listed by <info>outbound:debug</info>.
            HELP);
        $this->addArgument('connection', InputArgument::REQUIRED, 'Connection name, see outbound:debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('connection');
        if (!is_string($name)) {
            return Command::INVALID;
        }
        $this->registry->connection($name);
        $tag = CacheTags::connectionTag($name);
        $this->invalidator->invalidate([$tag]);
        $output->writeln(sprintf('Flushed tag %s.', $tag));

        return Command::SUCCESS;
    }
}
