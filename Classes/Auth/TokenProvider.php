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

use KonradMichalik\Typo3Outbound\Attribute\Auth\{OAuth2ClientCredentials, TokenLogin};
use KonradMichalik\Typo3Outbound\Context\{CallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Exception\{ApiTransportFailed, ApiUnauthorized};
use KonradMichalik\Typo3Outbound\Http\{HostGuard, RequestSpec};
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use KonradMichalik\Typo3Outbound\Runtime\RequestBudget;
use KonradMichalik\Typo3Outbound\Support\Clock;
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport, TransportOptions};
use KonradMichalik\Typo3Outbound\Value\SiteSetting;
use Throwable;

use function array_keys;
use function array_map;
use function array_values;
use function hash;
use function http_build_query;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function max;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * TokenProvider.
 *
 * Client credentials grant or a login with credentials. One token per connection
 * and site, shared by all resources and kept until 30 seconds before it expires.
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

    public function token(ConnectionMetadata $connection, OAuth2ClientCredentials|TokenLogin $auth, TransportOptions $options): string
    {
        $url = $this->url($connection, $auth);
        $key = $this->key($connection, $auth, $url);
        $now = $this->clock->now();
        if (isset($this->memo[$key]) && $this->memo[$key]['expires'] > $now) {
            return $this->memo[$key]['token'];
        }
        $cached = $this->store->get($key);
        if (is_array($cached) && is_string($cached['token'] ?? null) && is_numeric($cached['expires'] ?? null) && $cached['expires'] > $now) {
            $this->memo[$key] = ['token' => $cached['token'], 'expires' => (float) $cached['expires']];

            return $cached['token'];
        }

        $this->guardHost($connection, $auth, $url);
        $duration = $this->budget->attemptDuration(null);
        // Symfony reads a max_duration of 0 as unlimited.
        if ($duration <= 0.0) {
            throw new ApiTransportFailed(sprintf('Connection %s: the time budget of this request is exhausted, no token requested.', $connection->name), 1794700072, $connection->name.'::token');
        }
        [$token, $lifetime] = $this->fetch($connection, $auth, $this->spec($auth, $url), $options->withMaxDuration($duration));
        $ttl = max(1, $lifetime - self::EXPIRY_MARGIN);
        $this->memo[$key] = ['token' => $token, 'expires' => $this->clock->now() + $ttl];
        $this->store->set($key, $this->memo[$key], $ttl);

        return $token;
    }

    public function invalidate(ConnectionMetadata $connection): void
    {
        $auth = $connection->auth;
        if (!$auth instanceof OAuth2ClientCredentials && !$auth instanceof TokenLogin) {
            return;
        }
        $key = $this->key($connection, $auth, $this->url($connection, $auth));
        unset($this->memo[$key]);
        $this->store->delete($key);
    }

    /**
     * @return array{string, int} token and lifetime in seconds
     */
    private function fetch(ConnectionMetadata $connection, OAuth2ClientCredentials|TokenLogin $auth, RequestSpec $spec, TransportOptions $options): array
    {
        [$field, $failedCode, $noTokenCode] = $auth instanceof TokenLogin ? [$auth->tokenField, 1794700242, 1794700243] : ['access_token', 1794700070, 1794700071];
        $endpoint = $connection->name.'::token';
        try {
            $response = $this->transport->request(new Call($endpoint, [], $spec, $options));
            $status = $response->getStatusCode();
            $payload = json_decode($response->getContent(false), true);
        } catch (Throwable $exception) {
            throw new ApiUnauthorized(sprintf('Connection %s: token request failed.', $connection->name), $failedCode, $endpoint, null, null, $exception);
        }
        $token = is_array($payload) ? ($payload[$field] ?? null) : null;
        if ($status >= 400 || !is_string($token) || '' === $token) {
            throw new ApiUnauthorized(sprintf('Connection %s: token request answered %d without a token.', $connection->name, $status), $noTokenCode, $endpoint, $status);
        }

        return [$token, self::lifetime($auth, $payload)];
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function lifetime(OAuth2ClientCredentials|TokenLogin $auth, array $payload): int
    {
        if ($auth instanceof TokenLogin) {
            return $auth->ttl;
        }

        // RFC 6749 asks for a number, but some servers send it as a string.
        return is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 300;
    }

    private function spec(OAuth2ClientCredentials|TokenLogin $auth, string $url): RequestSpec
    {
        if ($auth instanceof TokenLogin) {
            $body = array_map(fn ($value): string => $this->values->resolve($value), $auth->body);

            return new RequestSpec('POST', $url, ['Content-Type' => 'application/json', 'Accept' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR), array_map('strval', array_keys($body)));
        }
        $form = ['grant_type' => 'client_credentials', 'client_id' => $this->values->resolve($auth->clientId), 'client_secret' => $this->values->resolve($auth->clientSecret)];
        if (null !== $auth->scope) {
            $form['scope'] = $auth->scope;
        }

        return new RequestSpec('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query($form), ['client_secret']);
    }

    private function url(ConnectionMetadata $connection, OAuth2ClientCredentials|TokenLogin $auth): string
    {
        $url = $this->values->resolve($auth instanceof TokenLogin ? $auth->url : $auth->tokenUrl);
        if ($auth instanceof TokenLogin && str_starts_with($url, '/')) {
            return rtrim($this->values->resolve($connection->definition->baseUri), '/').$url;
        }

        return $url;
    }

    /**
     * Credentials of a login go only to hosts the connection allows, like every other call;
     * a literal or environment token URL is trusted unless allowedHosts says otherwise.
     */
    private function guardHost(ConnectionMetadata $connection, OAuth2ClientCredentials|TokenLogin $auth, string $url): void
    {
        $definition = $connection->definition;
        if ($auth instanceof TokenLogin) {
            $relative = str_starts_with($this->values->resolve($auth->url), '/');
            HostGuard::check($url, $relative ? $this->values->resolve($definition->baseUri) : $url, $definition->allowedHosts, $definition->allowPrivateNetwork, $connection->name.' token');

            return;
        }
        HostGuard::check($url, $url, $auth->tokenUrl instanceof SiteSetting ? $definition->allowedHosts : [], $definition->allowPrivateNetwork, $connection->name.' token');
    }

    /**
     * A token belongs to the host and account it was issued for, so switching either never reuses it.
     */
    private function key(ConnectionMetadata $connection, OAuth2ClientCredentials|TokenLogin $auth, string $url): string
    {
        $principal = $this->values->resolve($auth instanceof TokenLogin ? array_values($auth->body)[0] : $auth->clientId);

        return sprintf('token_%s_%s_%s', $connection->name, $this->context->siteIdentifier() ?? 'none', substr(hash('sha256', $url."\n".$principal), 0, 16));
    }
}
