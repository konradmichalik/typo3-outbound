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

namespace KonradMichalik\Typo3Outbound\Transport;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport.
 *
 * Sends a call and returns the lazy Symfony response. Network errors surface
 * when the response is read.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface Transport
{
    public function request(Call $call): ResponseInterface;
}
