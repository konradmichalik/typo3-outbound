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

use KonradMichalik\Typo3Outbound\Mapping\Normalizer;
use KonradMichalik\Typo3Outbound\Metadata\{ParameterKind, ParameterMetadata, Registry};
use KonradMichalik\Typo3Outbound\Runtime\{CallFactory, EndpointInvoker};
use KonradMichalik\Typo3Outbound\Support\Redactor;
use KonradMichalik\Typo3Outbound\Typo3\RequestContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Traversable;

use function array_diff;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function iterator_to_array;
use function json_decode;
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * CallCommand.
 *
 *     outbound:call PetApi::find petId=10 --site=main
 *     outbound:call PetApi::add 'pet={"name":"Rex"}' --dry-run
 *
 * With --dry-run the request is built (including authentication) and shown with
 * redacted credentials, but not sent; the counterpart of routing:match.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CallCommand extends Command
{
    public function __construct(
        private readonly Registry $registry,
        private readonly EndpointLocator $endpoints,
        private readonly EndpointInvoker $invoker,
        private readonly CallFactory $calls,
        private readonly RequestContext $context,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Calls one endpoint through the full runtime (authentication, retries, cache, limits)
            and prints the mapped result as JSON. Arguments are name=value pairs named after the
            method parameters; objects and lists are given as JSON. Each name may appear once, and
            unknown names fail with the list of valid ones.

              <info>%command.full_name% PetApi::find petId=10</info>
              <info>%command.full_name% PetApi::find petId=10 --site=main</info>     site settings and secrets of a site
              <info>%command.full_name% PetApi::add 'pet={"name":"Rex"}' --dry-run</info>

            With --dry-run the request is built, including authentication, and shown with redacted
            credentials, but not sent. Exit code 2 on invalid arguments.
            HELP);
        $this->addArgument('endpoint', InputArgument::REQUIRED, 'Endpoint key, e.g. PetApi::find')
            ->addArgument('arguments', InputArgument::IS_ARRAY, 'name=value pairs; JSON values for objects and lists')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site identifier for site settings and secrets')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Build and show the request without sending it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('endpoint');
        $endpoint = $this->endpoints->byKey(is_string($key) ? $key : '');
        $named = [];
        foreach ((array) $input->getArgument('arguments') as $pair) {
            $parts = explode('=', is_string($pair) ? $pair : '', 2);
            if (2 !== count($parts) || array_key_exists($parts[0], $named)) {
                $output->writeln(sprintf('<error>Arguments are name=value pairs with each name once, got "%s".</error>', is_string($pair) ? $pair : ''));

                return Command::INVALID;
            }
            [$name, $value] = $parts;
            $named[$name] = in_array($value[0] ?? '', ['{', '['], true) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        }
        $known = array_map(static fn (ParameterMetadata $parameter): string => $parameter->name, $endpoint->parameters);
        $unknown = array_diff(array_keys($named), $known);
        if ([] !== $unknown) {
            $output->writeln(sprintf('<error>Unknown argument %s. %s takes: %s</error>', implode(', ', $unknown), $endpoint->key, implode(', ', $known)));

            return Command::INVALID;
        }
        $positional = [];
        foreach ($endpoint->parameters as $parameter) {
            $positional[] = $named[$parameter->name] ?? null;
        }

        $run = function () use ($input, $output, $endpoint, $named, $positional): int {
            if (true === $input->getOption('dry-run')) {
                $request = $this->calls->create($endpoint, $this->registry->connection($endpoint->connection), $named)->request;
                $output->writeln(sprintf('%s %s', $request->method, Redactor::url($request->url, $request->sensitiveKeys)));
                foreach (Redactor::headers($request->headers, $request->sensitiveKeys) as $name => $value) {
                    $output->writeln(sprintf('%s: %s', $name, $value));
                }
                if (is_string($request->body)) {
                    $sensitive = [] !== array_filter($endpoint->parametersOf(ParameterKind::Body), static fn (ParameterMetadata $parameter): bool => $parameter->sensitive);
                    $output->writeln('');
                    $output->writeln($sensitive ? Redactor::MASK : $request->body);
                }

                return Command::SUCCESS;
            }
            $result = $this->invoker->call($endpoint->interface, $endpoint->method, $positional);
            if ($result instanceof \KonradMichalik\Typo3Outbound\Value\Deferred) {
                $result = $result->get();
            }
            if ($result instanceof Traversable) {
                $result = iterator_to_array($result, false);
            }
            $output->writeln(json_encode((new Normalizer())->forBody($result), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        };

        $site = $input->getOption('site');

        return is_string($site) ? $this->context->withSite($site, $run) : $run();
    }
}
