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

namespace KonradMichalik\Typo3Outbound\Context;

use KonradMichalik\Typo3Outbound\Exception\MissingContext;
use KonradMichalik\Typo3Outbound\Value\{Env, Secret, Setting, ValueSource};

use function getenv;
use function is_scalar;
use function is_string;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * ValueResolver.
 *
 * Resolves Env, Setting and Secret at call time. A Secret's setting must
 * hold a reference `env:NAME`; anything else is rejected, so a secret written
 * into settings.yaml by mistake fails loudly instead of being used.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ValueResolver
{
    public function __construct(
        private CallContext $context,
    ) {}

    public function resolve(string|ValueSource $value): string
    {
        return match (true) {
            is_string($value) => $value,
            $value instanceof Env => $this->env($value),
            $value instanceof Setting => $this->setting($value->key),
            $value instanceof Secret => $this->secret($value),
            default => throw new MissingContext(sprintf('Unsupported value source %s.', $value::class), 1794700051),
        };
    }

    private function env(Env $env): string
    {
        $name = $env->name;
        $value = getenv($name);
        if (false === $value || '' === $value) {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }
        // Values read from secret files usually end with a newline, which no header accepts.
        $value = is_string($value) ? rtrim($value, "\r\n") : null;
        if (null !== $value && '' !== $value) {
            return $value;
        }
        if (null !== $env->default && '' !== $env->default) {
            return $env->default;
        }

        throw new MissingContext(sprintf('Environment variable "%s" is not set.', $name), 1794700052);
    }

    private function setting(string $key): string
    {
        $value = $this->context->setting($key);
        if (!is_scalar($value) || '' === (string) $value) {
            throw new MissingContext(sprintf('Setting "%s" is empty for scope "%s".', $key, $this->context->scope() ?? '-'), 1794700053);
        }

        return (string) $value;
    }

    private function secret(Secret $secret): string
    {
        $reference = $this->setting($secret->key);
        if (!str_starts_with($reference, 'env:')) {
            throw new MissingContext(sprintf('Setting "%s" must reference an environment variable as "env:NAME", not hold the secret itself.', $secret->key), 1794700054);
        }

        return $this->env(new Env(substr($reference, 4)));
    }
}
