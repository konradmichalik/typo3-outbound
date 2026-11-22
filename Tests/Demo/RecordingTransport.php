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

namespace KonradMichalik\Typo3Outbound\Tests\Demo;

use KonradMichalik\Typo3Outbound\Support\Redactor;
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * RecordingTransport.
 *
 * Records every attempt the runtime sends, so the demo can show retries and
 * cache hits (a cache hit sends nothing).
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RecordingTransport implements Transport
{
    /** @var list<array{endpoint: string, method: string, url: string, response: ResponseInterface}> */
    public array $attempts = [];

    public function __construct(
        private readonly Transport $inner,
    ) {}

    public function request(Call $call): ResponseInterface
    {
        $response = $this->inner->request($call);
        $this->attempts[] = [
            'endpoint' => $call->endpoint,
            'method' => $call->request->method,
            'url' => Redactor::url($call->request->url, $call->request->sensitiveKeys),
            'response' => $response,
        ];

        return $response;
    }
}
