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

namespace KonradMichalik\Typo3Outbound\Transport;

use KonradMichalik\Typo3Outbound\Http\RequestSpec;

/**
 * Call.
 *
 * One attempt of an endpoint call as seen by the transport. Mocks match on the
 * endpoint key and the named arguments, never on URLs.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Call
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $endpoint,
        public array $arguments,
        public RequestSpec $request,
        public TransportOptions $options,
    ) {}
}
