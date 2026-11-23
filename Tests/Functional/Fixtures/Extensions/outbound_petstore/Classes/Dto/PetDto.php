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

namespace KonradMichalik\OutboundPetstore\Dto;

/**
 * Pet, as defined by components/schemas/Pet of the Petstore OpenAPI document.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class PetDto
{
    /**
     * @param list<string> $photoUrls
     * @param list<TagDto> $tags
     */
    public function __construct(
        public string $name,
        public array $photoUrls = [],
        public ?int $id = null,
        public ?CategoryDto $category = null,
        public array $tags = [],
        public ?PetStatus $status = null,
    ) {}
}
