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

namespace KonradMichalik\Typo3Outbound\Value;

use function is_float;
use function is_numeric;
use function sprintf;
use function var_export;

/**
 * Decimal.
 *
 * A decimal number kept as string, for amounts that must not lose precision.
 * Accepts strings and numbers from the response.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Decimal implements \Stringable
{
    public string $value;

    public function __construct(int|float|string $value)
    {
        $string = is_float($value) ? var_export($value, true) : (string) $value;
        if (!is_numeric($string)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a decimal number.', $string), 1794700040);
        }
        $this->value = $string;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
