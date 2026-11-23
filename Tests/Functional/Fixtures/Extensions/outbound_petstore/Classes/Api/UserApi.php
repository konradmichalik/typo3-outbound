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

use KonradMichalik\OutboundPetstore\Dto\UserDto;
use KonradMichalik\Typo3Outbound\Attribute as Outbound;

/**
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Resource('/user')]
interface UserApi extends PetstoreApi
{
    #[Outbound\Get('/login')]
    public function login(#[Outbound\Query] string $username, #[Outbound\Query] #[Outbound\Sensitive] string $password): string;

    #[Outbound\Get('/logout')]
    public function logout(): void;

    #[Outbound\Get('/{username}')]
    #[Outbound\OnStatus(404, returns: null)]
    public function find(string $username): ?UserDto;
}
