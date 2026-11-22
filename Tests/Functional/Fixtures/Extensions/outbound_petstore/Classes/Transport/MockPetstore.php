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

namespace KonradMichalik\OutboundPetstore\Transport;

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi, UserApi};
use KonradMichalik\Typo3Outbound\Testing\MockTransport;

/**
 * MockPetstore.
 *
 * Answers the demo from the fixture responses, for working without network.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MockPetstore
{
    public static function create(): MockTransport
    {
        $responses = __DIR__.'/../../Resources/Private/Responses';
        $mock = new MockTransport();
        $mock->on(PetApi::class, 'find', ['petId' => 10])->respondWithFixture($responses.'/pet-10.json');
        $mock->on(PetApi::class, 'find', ['petId' => 11])->respondWithFixture($responses.'/pet-10.json');
        $mock->on(PetApi::class, 'find')->respondWith(404, 'Pet not found', ['content-type' => 'text/plain']);
        $mock->on(PetApi::class, 'findByStatus')->respondWithFixture($responses.'/pets-available.json');
        $mock->on(PetApi::class, 'add')->respondWithFixture($responses.'/pet-10.json');
        $mock->on(StoreApi::class, 'inventory')->respondWithFixture($responses.'/inventory.json');
        $mock->on(StoreApi::class, 'order', ['orderId' => 5])->respondWithFixture($responses.'/order-5.json');
        $mock->on(StoreApi::class, 'order')->respondWith(404, 'Order not found', ['content-type' => 'text/plain']);
        $mock->on(UserApi::class, 'find')->respondWithFixture($responses.'/user-theuser.json');

        return $mock;
    }
}
