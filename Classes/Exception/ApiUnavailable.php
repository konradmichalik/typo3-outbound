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

namespace KonradMichalik\Typo3Outbound\Exception;

/**
 * ApiUnavailable.
 *
 * The API answered 5xx after all retries, or the circuit breaker of the connection
 * is open. `$retryAt` tells when the next attempt is allowed.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ApiUnavailable extends ApiException
{
    public function __construct(
        string $message,
        int $code,
        string $endpoint = '',
        ?int $status = null,
        ?string $requestId = null,
        public readonly ?int $retryAt = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $endpoint, $status, $requestId, $previous);
    }
}
