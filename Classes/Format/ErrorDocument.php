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

namespace KonradMichalik\Typo3Outbound\Format;

/**
 * ErrorDocument.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ErrorDocument
{
    /**
     * @param array<string, list<string>> $fieldErrors
     */
    public function __construct(
        public ?string $title = null,
        public ?string $detail = null,
        public array $fieldErrors = [],
    ) {}
}
