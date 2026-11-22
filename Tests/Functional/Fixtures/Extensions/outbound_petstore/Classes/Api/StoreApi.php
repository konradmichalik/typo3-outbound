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

use KonradMichalik\OutboundPetstore\Dto\OrderDto;
use KonradMichalik\Typo3Outbound\Attribute as Outbound;

#[Outbound\Resource('/store')]
interface StoreApi extends PetstoreApi
{
    /** @return array<string, int> */
    #[Outbound\Get('/inventory')]
    #[Outbound\HealthCheck]
    public function inventory(): array;

    #[Outbound\Post('/order', idempotencyKey: true)]
    public function placeOrder(#[Outbound\Body] OrderDto $order): OrderDto;

    #[Outbound\Get('/order/{orderId}')]
    #[Outbound\OnStatus(404, returns: null)]
    public function order(int $orderId): ?OrderDto;

    #[Outbound\Delete('/order/{orderId}')]
    public function deleteOrder(int $orderId): void;
}
