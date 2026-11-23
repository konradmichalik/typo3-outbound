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

namespace KonradMichalik\OutboundPetstore\Controller;

use Closure;
use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi};
use KonradMichalik\OutboundPetstore\Dto\{CategoryDto, PetDto, PetStatus};
use KonradMichalik\Typo3Outbound\Exception\ApiException;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

use function array_slice;

/**
 * Petstore frontend plugin. API calls happen in plain PHP; the page cache
 * receives the API cache tags, so flushing `outbound_petstore` clears it too.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PetsController extends ActionController
{
    public function __construct(
        private readonly PetApi $pets,
        private readonly StoreApi $store,
    ) {}

    public function listAction(string $status = 'available'): ResponseInterface
    {
        $selected = PetStatus::tryFrom($status) ?? PetStatus::Available;

        return $this->render('list', fn (): array => [
            'pets' => array_slice($this->pets->findByStatus($selected), 0, 50),
            'status' => $selected->value,
            'statuses' => array_map(static fn (PetStatus $case): string => $case->value, PetStatus::cases()),
        ]);
    }

    public function showAction(int $pet): ResponseInterface
    {
        return $this->render('show', fn (): array => ['pet' => $this->pets->find($pet)]);
    }

    public function newAction(): ResponseInterface
    {
        $this->view->assign('statuses', array_map(static fn (PetStatus $case): string => $case->value, PetStatus::cases()));

        return $this->htmlResponse();
    }

    public function createAction(string $name, string $category = '', string $photoUrl = '', string $status = 'available'): ResponseInterface
    {
        return $this->render('show', fn (): array => ['created' => true, 'pet' => $this->pets->add(new PetDto(
            name: $name,
            photoUrls: '' !== $photoUrl ? [$photoUrl] : [],
            id: random_int(1_000_000, 9_000_000),
            category: '' !== $category ? new CategoryDto(1, $category) : null,
            status: PetStatus::tryFrom($status) ?? PetStatus::Available,
        ))]);
    }

    public function inventoryAction(): ResponseInterface
    {
        return $this->render('inventory', fn (): array => ['inventory' => $this->store->inventory()]);
    }

    /**
     * @param Closure(): array<string, mixed> $variables
     */
    private function render(string $template, Closure $variables): ResponseInterface
    {
        try {
            $this->view->assignMultiple($variables());
        } catch (ApiException $exception) {
            $this->view->assign('error', [
                'type' => (new ReflectionClass($exception))->getShortName(),
                'message' => $exception->getMessage(),
                'endpoint' => $exception->endpoint,
                'status' => $exception->status,
            ]);
        }

        return $this->htmlResponse();
    }
}
