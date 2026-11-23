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

namespace KonradMichalik\Typo3Outbound\DependencyInjection;

use KonradMichalik\Typo3Outbound\Attribute\{Connection, Resource};
use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function file_get_contents;
use function interface_exists;
use function is_dir;
use function sprintf;

/**
 * OutboundServices.
 *
 * Registers API interfaces with the container explicitly. Only needed for
 * interfaces that no Services.yaml resource covers or that sit in an exclude
 * path; OutboundCompilerPass picks up the scanned ones itself. Called from an extension's Configuration/Services.php:
 *
 *     return static function (ContainerConfigurator $c, ContainerBuilder $builder): void {
 *         OutboundServices::discover($builder, __DIR__.'/../Classes/Api');
 *     };
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class OutboundServices
{
    public const TAG = 'outbound.api';

    /**
     * Registers every interface with #[Connection] or #[Resource] below the directory.
     *
     * @return list<class-string>
     */
    public static function discover(ContainerBuilder $builder, string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidDefinition(sprintf('Directory "%s" does not exist.', $directory), 1794700180);
        }
        $registered = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $interface = self::interfaceIn((string) file_get_contents($file->getPathname()));
            if (null === $interface || !interface_exists($interface)) {
                continue;
            }
            if (!self::isApi(new ReflectionClass($interface))) {
                continue;
            }
            self::register($builder, $interface);
            $registered[] = $interface;
        }

        return $registered;
    }

    /**
     * @param class-string ...$interfaces
     */
    public static function register(ContainerBuilder $builder, string ...$interfaces): void
    {
        foreach ($interfaces as $interface) {
            $builder->register($interface, $interface)->addTag(self::TAG);
        }
    }

    /**
     * @internal
     *
     * @param ReflectionClass<object> $reflection
     */
    public static function isApi(ReflectionClass $reflection): bool
    {
        return $reflection->isInterface()
            && ([] !== $reflection->getAttributes(Connection::class) || [] !== $reflection->getAttributes(Resource::class));
    }

    /**
     * @return class-string|null
     */
    private static function interfaceIn(string $code): ?string
    {
        // Tokens, not a regex: an attribute on the same line, a bracketed namespace or
        // the word "interface" in a comment must not change the result.
        $tokens = PhpToken::tokenize($code);
        $namespace = '';
        foreach ($tokens as $index => $token) {
            if ($token->is(\T_NAMESPACE)) {
                $name = self::nextToken($tokens, $index);
                $namespace = null !== $name && $name->is([\T_NAME_QUALIFIED, \T_STRING]) ? $name->text.'\\' : '';
            }
            if ($token->is(\T_INTERFACE)) {
                $name = self::nextToken($tokens, $index);
                if (null === $name || !$name->is(\T_STRING)) {
                    return null;
                }

                /** @var class-string $interface */
                $interface = $namespace.$name->text;

                return $interface;
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function nextToken(array $tokens, int $index): ?PhpToken
    {
        for ($next = $index + 1; isset($tokens[$next]); ++$next) {
            if (!$tokens[$next]->isIgnorable()) {
                return $tokens[$next];
            }
        }

        return null;
    }
}
