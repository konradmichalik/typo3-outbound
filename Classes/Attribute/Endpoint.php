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

namespace KonradMichalik\Typo3Outbound\Attribute;

/**
 * Endpoint.
 *
 * Base of the verb attributes. A method carries exactly one of them.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
abstract readonly class Endpoint
{
    /**
     * @param string      $path           Path below the resource prefix, placeholders as {name}
     * @param string|null $unwrap         Dot path of the payload inside an envelope, e.g. "data"
     * @param bool|null   $idempotent     Overrides the idempotency derived from the HTTP method
     * @param bool        $idempotencyKey Send an Idempotency-Key header, reused across retries
     * @param bool        $ifMatch        Send the last known ETag of this resource as If-Match
     */
    public function __construct(
        public string $path = '',
        public ?string $unwrap = null,
        public ?bool $idempotent = null,
        public bool $idempotencyKey = false,
        public bool $ifMatch = false,
    ) {}

    abstract public function method(): string;
}
