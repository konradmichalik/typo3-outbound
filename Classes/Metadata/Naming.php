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

namespace KonradMichalik\Typo3Outbound\Metadata;

use function preg_replace;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Naming.
 *
 * Connection names and endpoint keys appear in logs, cache tags, commands and
 * tests, so they follow one rule: `CrmApi` becomes `crm`, `ContactsApi::find`
 * identifies an endpoint.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Naming
{
    public static function connectionName(string $interfaceShortName, ?string $explicit = null): string
    {
        if (null !== $explicit && '' !== $explicit) {
            return $explicit;
        }
        $name = str_ends_with($interfaceShortName, 'Api') && 'Api' !== $interfaceShortName
            ? substr($interfaceShortName, 0, -3)
            : $interfaceShortName;
        $name = (string) preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $name);
        $name = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name);

        return strtolower($name);
    }

    public static function endpointKey(string $interfaceShortName, string $method): string
    {
        return $interfaceShortName.'::'.$method;
    }

    public static function isValidConnectionName(string $name): bool
    {
        return 1 === preg_match('/^[a-z][a-z0-9_]*$/', $name) && strlen($name) <= 64;
    }
}
