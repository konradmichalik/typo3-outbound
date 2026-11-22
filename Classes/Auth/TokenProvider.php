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

namespace KonradMichalik\Typo3Outbound\Auth;

use KonradMichalik\Typo3Outbound\Attribute\Auth\OAuth2ClientCredentials;
use KonradMichalik\Typo3Outbound\Context\{CallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Exception\ApiUnauthorized;
use KonradMichalik\Typo3Outbound\Http\{HostGuard, RequestSpec};
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use KonradMichalik\Typo3Outbound\Runtime\RequestBudget;
use KonradMichalik\Typo3Outbound\Support\Clock;
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport, TransportOptions};
use KonradMichalik\Typo3Outbound\Value\SiteSetting;

use function http_build_query;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function sprintf;

/**
 * TokenProvider.
 *
 * Client credentials grant. One token per connection and site, shared by all
 * resources and kept until 30 seconds before it expires.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class TokenProvider
{
    private const EXPIRY_MARGIN = 30;

    /** @var array<string, array{token: string, expires: float}> */
    private array $memo = [];

    public function __construct(
        private readonly Transport $transport,
        private readonly StateStore $store,
        private readonly ValueResolver $values,
        private readonly CallContext $context,
        private readonly Clock $clock,
        private readonly RequestBudget $budget,
    ) {}

    public function token(ConnectionMetadata $connection, OAuth2ClientCredentials $auth, TransportOptions $options): string
    {
        $key = $this->key($connection);
        $now = $this->clock->now();
        if (isset($this->memo[$key]) && $this->memo[$key]['expires'] > $now) {
            return $this->memo[$key]['token'];
        }
        $cached = $this->store->get($key);
        if (is_array($cached) && is_string($cached['token'] ?? null) && is_numeric($cached['expires'] ?? null) && $cached['expires'] > $now) {
            $this->memo[$key] = ['token' => $cached['token'], 'expires' => (float) $cached['expires']];

            return $cached['token'];
        }

        $tokenUrl = $this->values->resolve($auth->tokenUrl);
        // A literal or environment value is trusted; one from site settings must match the allow list.
        $allowedHosts = $auth->tokenUrl instanceof SiteSetting ? $connection->definition->allowedHosts : [];
        HostGuard::check($tokenUrl, $tokenUrl, $allowedHosts, $connection->definition->allowPrivateNetwork, $connection->name.' token');
        $options = $options->withMaxDuration($this->budget->attemptDuration(null));
        $form = ['grant_type' => 'client_credentials', 'client_id' => $this->values->resolve($auth->clientId), 'client_secret' => $this->values->resolve($auth->clientSecret)];
        if (null !== $auth->scope) {
            $form['scope'] = $auth->scope;
        }
        $spec = new RequestSpec('POST', $tokenUrl, ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query($form), ['client_secret']);

        try {
            $response = $this->transport->request(new Call($connection->name.'::token', [], $spec, $options));
            $status = $response->getStatusCode();
            $payload = json_decode($response->getContent(false), true);
        } catch (\Throwable $exception) {
            throw new ApiUnauthorized(sprintf('Connection %s: token request failed.', $connection->name), 1794700070, $connection->name.'::token', null, null, $exception);
        }
        if ($status >= 400 || !is_array($payload) || !is_string($payload['access_token'] ?? null)) {
            throw new ApiUnauthorized(sprintf('Connection %s: token request answered %d without an access token.', $connection->name, $status), 1794700071, $connection->name.'::token', $status);
        }
        $lifetime = is_int($payload['expires_in'] ?? null) ? $payload['expires_in'] : 300;
        $ttl = max(1, $lifetime - self::EXPIRY_MARGIN);
        $this->memo[$key] = ['token' => $payload['access_token'], 'expires' => $this->clock->now() + $ttl];
        $this->store->set($key, $this->memo[$key], $ttl);

        return $payload['access_token'];
    }

    public function invalidate(ConnectionMetadata $connection): void
    {
        unset($this->memo[$this->key($connection)]);
        $this->store->delete($this->key($connection));
    }

    private function key(ConnectionMetadata $connection): string
    {
        return sprintf('token_%s_%s', $connection->name, $this->context->siteIdentifier() ?? 'none');
    }
}
