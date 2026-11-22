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

use Symfony\Component\HttpClient\{HttpClient, NoPrivateNetworkHttpClient};
use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface};

use function min;
use function sprintf;

/**
 * SymfonyTransport.
 *
 * Symfony HttpClient with redirects disabled, a total duration per attempt
 * (`max_duration`), a response size limit and NoPrivateNetworkHttpClient unless
 * the connection opts out for intranet APIs.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class SymfonyTransport implements Transport
{
    private ?HttpClientInterface $guarded = null;

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {}

    public static function create(): self
    {
        return new self(HttpClient::create());
    }

    public function request(Call $call): ResponseInterface
    {
        $options = $call->options;
        $client = $options->allowPrivateNetwork ? $this->client : ($this->guarded ??= new NoPrivateNetworkHttpClient($this->client));
        $limit = $options->maxResponseBytes;

        return $client->request($call->request->method, $call->request->url, [
            'headers' => $call->request->headers,
            'body' => $call->request->body ?? '',
            'max_duration' => $options->maxDuration,
            'timeout' => min($options->maxDuration, 30.0),
            'max_redirects' => 0,
            'verify_peer' => $options->verifyTls,
            'verify_host' => $options->verifyTls,
            'on_progress' => static function (int $downloaded) use ($limit, $call): void {
                if ($downloaded > $limit) {
                    throw new \RuntimeException(sprintf('%s: response exceeds %d bytes.', $call->endpoint, $limit), 1794700080);
                }
            },
        ]);
    }
}
