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

namespace KonradMichalik\OutboundPetstore\Api;

use KonradMichalik\OutboundPetstore\Dto\{PetDto, PetStatus};
use KonradMichalik\Typo3Outbound\Attribute as Outbound;
use KonradMichalik\Typo3Outbound\Value\Deferred;

#[Outbound\Resource('/pet')]
interface PetApi extends PetstoreApi
{
    #[Outbound\Get('/{petId}')]
    #[Outbound\Cache(lifetime: 60, tags: ['petstore_pet_{petId}'], staleOnError: 300)]
    #[Outbound\OnStatus(404, returns: null)]
    public function find(int $petId): ?PetDto;

    /** @return Deferred<PetDto> */
    #[Outbound\Get('/{petId}')]
    public function findLater(int $petId): Deferred;

    /** @return list<PetDto> */
    #[Outbound\Get('/findByStatus')]
    #[Outbound\Cache(lifetime: 30, tags: ['petstore_pets'])]
    public function findByStatus(#[Outbound\Query] PetStatus $status = PetStatus::Available): array;

    /**
     * @param list<string> $tags
     *
     * @return list<PetDto>
     */
    #[Outbound\Get('/findByTags')]
    public function findByTags(#[Outbound\Query] array $tags): array;

    #[Outbound\Post]
    #[Outbound\InvalidatesCache(tags: ['petstore_pets'])]
    public function add(#[Outbound\Body] PetDto $pet): PetDto;

    #[Outbound\Put]
    #[Outbound\InvalidatesCache(tags: ['petstore_pets'])]
    public function update(#[Outbound\Body] PetDto $pet): PetDto;

    #[Outbound\Delete('/{petId}')]
    #[Outbound\InvalidatesCache(tags: ['petstore_pet_{petId}', 'petstore_pets'])]
    public function delete(int $petId, #[Outbound\Header('api_key')] #[Outbound\Sensitive] ?string $apiKey = null): void;
}
