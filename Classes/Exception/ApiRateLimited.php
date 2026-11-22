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
 * ApiRateLimited.
 *
 * The API answered 429 after all retries, or a local or server-announced limit is
 * exhausted. In a web request this is thrown instead of waiting.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ApiRateLimited extends ApiException
{
    public function __construct(
        string $message,
        int $code,
        string $endpoint = '',
        ?int $status = null,
        ?string $requestId = null,
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $endpoint, $status, $requestId, $previous);
    }
}
