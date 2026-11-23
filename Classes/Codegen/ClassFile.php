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

namespace KonradMichalik\Typo3Outbound\Codegen;

use function array_map;
use function implode;
use function mb_scrub;
use function preg_replace;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trim;
use function usort;
use function var_export;

/**
 * ClassFile.
 *
 * Renders a readonly DTO or a backed enum. Values are always exported with
 * var_export(), never interpolated.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ClassFile
{
    /**
     * Text from a spec is untrusted: it must not be able to close the comment it is placed in.
     * Invalid UTF-8 is replaced, control characters other than tab and newline and the
     * bidirectional overrides that can disguise code are dropped, a comment terminator is defused.
     */
    public static function docText(string $text): string
    {
        $text = (string) preg_replace('/(?![\t\n])\p{Cc}|[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', mb_scrub(str_replace("\r", '', $text)));

        return trim(str_replace('*/', '*\\/', $text));
    }

    /**
     * Same, collapsed to a single line, for places that prefix only one line.
     */
    public static function docLine(string $text): string
    {
        return (string) preg_replace('/\s+/', ' ', self::docText($text));
    }

    /**
     * @param list<array{name: string, type: string, doc: ?string, optional: bool}> $properties
     */
    public static function dto(string $namespace, string $class, array $properties, ?string $description = null): string
    {
        usort($properties, static fn (array $a, array $b): int => (int) $a['optional'] <=> (int) $b['optional']);
        $docs = [];
        $parameters = [];
        foreach ($properties as $property) {
            if (null !== $property['doc']) {
                $docs[] = sprintf('     * @param %s $%s', $property['doc'], $property['name']);
            }
            $type = $property['optional'] && 'mixed' !== $property['type'] && !str_starts_with($property['type'], '?') ? '?'.$property['type'] : $property['type'];
            $parameters[] = sprintf('        public %s $%s%s,', $type, $property['name'], $property['optional'] ? ' = null' : '');
        }
        $docBlock = [] !== $docs ? "    /**\n".implode("\n", $docs)."\n     */\n" : '';

        return self::file($namespace, sprintf(
            "%sfinal readonly class %s\n{\n%s    public function __construct(\n%s\n    ) {}\n}\n",
            null !== $description ? '/**'."\n".' * '.str_replace("\n", "\n * ", self::docText($description))."\n */\n" : '',
            $class,
            $docBlock,
            implode("\n", $parameters),
        ));
    }

    /**
     * @param list<string> $values
     */
    public static function enum(string $namespace, string $class, array $values): string
    {
        $cases = [];
        foreach ($values as $value) {
            $cases[] = sprintf('    case %s = %s;', Names::studly($value), var_export($value, true));
        }

        return self::file($namespace, sprintf("enum %s: string\n{\n%s\n}\n", $class, implode("\n", $cases)));
    }

    /**
     * @param list<string> $uses
     */
    public static function file(string $namespace, string $body, array $uses = []): string
    {
        $imports = [] !== $uses ? implode("\n", array_map(static fn (string $use): string => 'use '.$use.';', $uses))."\n\n" : '';

        return sprintf("<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\n%s%s", $namespace, $imports, $body);
    }
}
