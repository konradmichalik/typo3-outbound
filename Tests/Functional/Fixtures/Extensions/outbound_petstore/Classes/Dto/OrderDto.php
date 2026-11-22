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

final readonly class OrderDto
{
    public function __construct(
        public ?int $id = null,
        public ?int $petId = null,
        public ?int $quantity = null,
        public ?\DateTimeImmutable $shipDate = null,
        public ?OrderStatus $status = null,
        public ?bool $complete = null,
    ) {}
}
