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

use KonradMichalik\Typo3Outbound\Attribute as Outbound;

/**
 * Connection to the public Swagger Petstore (https://petstore3.swagger.io).
 *
 * The public instance needs no credentials for the endpoints used here. Its
 * state is shared by everyone, so live tests must not rely on specific data.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Outbound\Connection(baseUri: 'https://petstore3.swagger.io/api/v3')]
#[Outbound\Retry(maxRetries: 2)]
#[Outbound\Timeout(5)]
#[Outbound\CircuitBreaker(failureThreshold: 5, cooldown: 30)]
interface PetstoreApi {}
