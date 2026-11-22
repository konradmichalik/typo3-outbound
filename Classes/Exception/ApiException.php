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
 * ApiException.
 *
 * Base class of every failure caused by a call to an external API. Carries the
 * endpoint key and, where a response arrived, its status code. The response body
 * is never part of the message; it is only logged, redacted, on debug level.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
abstract class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code,
        public readonly string $endpoint = '',
        public readonly ?int $status = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
