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

namespace KonradMichalik\Typo3Outbound\Cache;

use BackedEnum;
use KonradMichalik\Typo3Outbound\Exception\InvalidDefinition;

use function array_key_exists;
use function array_unique;
use function array_values;
use function hash;
use function is_int;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;
use function substr;

/**
 * CacheTags.
 *
 * Renders tag templates like "crm_contact_{id}". Values that are not plain
 * `[A-Za-z0-9_-]` (at most 64 characters) are hashed instead of rewritten, so
 * "a.b" and "a_b" never share a tag. Use tag() in your own invalidation code
 * (e.g. a DataHandler hook) to produce exactly the tags the cache sets.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheTags
{
    /** Same as TYPO3's FrontendInterface::PATTERN_TAG. */
    public const PATTERN = '/^[a-zA-Z0-9_%\-&]{1,250}$/';

    private const PLACEHOLDER = '/\{(\w+)\}/';
    private const PLAIN_VALUE = '/^[A-Za-z0-9_-]{1,64}$/';
    private const HASHED_VALUE = '/^h[0-9a-f]{16}$/';

    /**
     * Renders one template with the given values.
     *
     * @param array<string, mixed> $arguments
     */
    public static function tag(string $template, array $arguments): string
    {
        $tag = (string) preg_replace_callback(
            self::PLACEHOLDER,
            static fn (array $match): string => self::value($template, $match[1], $arguments),
            $template,
        );
        if (1 !== preg_match(self::PATTERN, $tag)) {
            throw new InvalidDefinition(sprintf('Cache tag "%s" rendered from "%s" is not a valid TYPO3 cache tag.', $tag, $template), 1794700034);
        }

        return $tag;
    }

    /**
     * The connection tag plus all rendered templates.
     *
     * @param list<string>         $templates
     * @param array<string, mixed> $arguments
     *
     * @return list<string>
     */
    public static function render(string $connection, array $templates, array $arguments): array
    {
        $tags = [self::connectionTag($connection)];
        foreach ($templates as $template) {
            $tags[] = self::tag($template, $arguments);
        }

        return array_values(array_unique($tags));
    }

    public static function connectionTag(string $connection): string
    {
        return 'outbound_'.$connection;
    }

    public static function encode(int|string|BackedEnum $value): string
    {
        $string = $value instanceof BackedEnum ? (string) $value->value : (string) $value;

        return 1 === preg_match(self::PLAIN_VALUE, $string) && 1 !== preg_match(self::HASHED_VALUE, $string)
            ? $string
            : 'h'.substr(hash('xxh128', $string), 0, 16);
    }

    /**
     * @return list<string>
     */
    public static function placeholders(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $matches);

        return $matches[1];
    }

    /**
     * The template with every placeholder replaced by a plain value, for validation.
     */
    public static function staticPart(string $template): string
    {
        return (string) preg_replace(self::PLACEHOLDER, 'x', $template);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function value(string $template, string $name, array $arguments): string
    {
        $value = array_key_exists($name, $arguments) ? $arguments[$name] : null;
        if (!is_int($value) && !is_string($value) && !$value instanceof BackedEnum) {
            throw new InvalidDefinition(sprintf('Cache tag "%s": placeholder {%s} has no int, string or backed enum value.', $template, $name), 1794700035);
        }

        return self::encode($value);
    }
}
