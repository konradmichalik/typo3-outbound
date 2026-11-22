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

use KonradMichalik\Typo3Outbound\Enum\{BodyFormat, QueryStyle};

/**
 * ParameterMetadata.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ParameterMetadata
{
    public function __construct(
        public string $name,
        public int $position,
        public ParameterKind $kind,
        public string $wireName,
        public QueryStyle $style = QueryStyle::Form,
        public BodyFormat $bodyFormat = BodyFormat::Json,
        public bool $sensitive = false,
    ) {}
}
