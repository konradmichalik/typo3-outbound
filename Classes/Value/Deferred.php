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

use Closure;

/**
 * Deferred.
 *
 * The request is sent as soon as the endpoint is called; the response is read
 * and mapped on the first get(). Several deferred calls therefore run in parallel
 * until their results are needed.
 *
 * @template T
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Deferred
{
    private bool $resolved = false;

    /** @var T */
    private mixed $value = null;

    /**
     * @param Closure(): T $resolver
     */
    public function __construct(
        private readonly Closure $resolver,
    ) {}

    /**
     * @return T
     */
    public function get(): mixed
    {
        if (!$this->resolved) {
            $this->value = ($this->resolver)();
            $this->resolved = true;
        }

        return $this->value;
    }
}
