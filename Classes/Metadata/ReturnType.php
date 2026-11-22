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

/**
 * ReturnType.
 *
 * `signature` is a fully qualified type signature understood by the mapper,
 * e.g. `Vendor\Dto\PetDto`, `list<Vendor\Dto\PetDto>` or `?string`. For
 * iterables and deferred results it describes the item, respectively the
 * wrapped value.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ReturnType
{
    public function __construct(
        public ReturnKind $kind,
        public string $signature = 'mixed',
        public bool $nullable = false,
    ) {}
}
