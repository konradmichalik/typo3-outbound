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

use KonradMichalik\Typo3Outbound\Attribute\Auth\{ApiKey, Basic, Bearer, OAuth2ClientCredentials, TokenLogin};
use KonradMichalik\Typo3Outbound\Context\{CallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Enum\ApiKeyLocation;
use KonradMichalik\Typo3Outbound\Metadata\ConnectionMetadata;
use KonradMichalik\Typo3Outbound\Transport\TransportOptions;

use function base64_encode;
use function hash;
use function substr;

/**
 * Authenticator.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Authenticator
{
    public function __construct(
        private ValueResolver $values,
        private TokenProvider $tokens,
        private CallContext $context,
    ) {}

    public function credentials(ConnectionMetadata $connection, TransportOptions $options): Credentials
    {
        $auth = $connection->auth;
        $scope = $this->context->scope() ?? 'none';

        return match (true) {
            $auth instanceof Bearer => $this->bearer($this->values->resolve($auth->token), $scope),
            $auth instanceof Basic => $this->basic($this->values->resolve($auth->username), $this->values->resolve($auth->password), $scope),
            $auth instanceof ApiKey => $this->apiKey($auth, $this->values->resolve($auth->value), $scope),
            $auth instanceof OAuth2ClientCredentials, $auth instanceof TokenLogin => $this->bearer($this->tokens->token($connection, $auth, $options), $scope, $this->tokens->principal($auth)),
            default => new Credentials(identity: 'anonymous:'.$scope),
        };
    }

    public function invalidate(ConnectionMetadata $connection): bool
    {
        return $this->tokens->invalidate($connection);
    }

    private function bearer(string $token, string $scope, ?string $principal = null): Credentials
    {
        return new Credentials(['Authorization' => 'Bearer '.$token], [], self::identity($scope, $principal ?? $token));
    }

    private function basic(string $username, string $password, string $scope): Credentials
    {
        return new Credentials(['Authorization' => 'Basic '.base64_encode($username.':'.$password)], [], self::identity($scope, $username.':'.$password));
    }

    private function apiKey(ApiKey $auth, string $value, string $scope): Credentials
    {
        return ApiKeyLocation::Query === $auth->in
            ? new Credentials([], [$auth->name => $value], self::identity($scope, $value), [$auth->name])
            : new Credentials([$auth->name => $value], [], self::identity($scope, $value), [$auth->name]);
    }

    private static function identity(string $scope, string $secret): string
    {
        return $scope.':'.substr(hash('sha256', $secret), 0, 16);
    }
}
