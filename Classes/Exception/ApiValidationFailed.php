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
 * ApiValidationFailed.
 *
 * The API answered 400 or 422. Field errors are taken from the error document
 * when the response format provides them.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ApiValidationFailed extends ApiException
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        string $message,
        int $code,
        string $endpoint = '',
        ?int $status = null,
        ?string $requestId = null,
        public readonly array $fieldErrors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $endpoint, $status, $requestId, $previous);
    }
}
