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

namespace KonradMichalik\Typo3Outbound\Resilience;

use DateTimeImmutable;
use DateTimeZone;
use KonradMichalik\Typo3Outbound\Attribute\Retry;
use KonradMichalik\Typo3Outbound\Metadata\EndpointMetadata;

use function in_array;
use function is_numeric;
use function max;
use function min;
use function random_int;
use function time;
use function trim;

/**
 * RetryPolicy.
 *
 * Unlike Symfony's RetryableHttpClient, a Retry-After header can never extend
 * the wait beyond the cap of the execution context, and non-idempotent requests
 * are never repeated unless explicitly forced.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RetryPolicy
{
    // DateTimeInterface::RFC7231 has this value but is deprecated since PHP 8.5.
    private const HTTP_DATE = 'D, d M Y H:i:s \G\M\T';

    public function __construct(
        private ?Retry $retry,
        private bool $idempotent,
    ) {}

    public static function for(EndpointMetadata $endpoint): self
    {
        return new self($endpoint->retry, $endpoint->idempotent);
    }

    public function allows(int $attempt, ?int $status): bool
    {
        if (null === $this->retry || $attempt > $this->retry->maxRetries) {
            return false;
        }
        if (!$this->idempotent && !$this->retry->force) {
            return false;
        }

        return null === $status || in_array($status, $this->retry->statusCodes, true);
    }

    /**
     * Seconds to wait before the given retry attempt (1-based). Null means the
     * server asks for longer than the cap allows: give up instead of waiting.
     */
    public function delay(int $attempt, ?string $retryAfter, float $cap, ?float $now = null): ?float
    {
        $retry = $this->retry ?? new Retry();
        if (null !== $retryAfter) {
            $seconds = self::parseRetryAfter($retryAfter, $now);
            if (null !== $seconds) {
                return $seconds > $cap ? null : $seconds;
            }
        }
        $base = $retry->delayMs * ($retry->multiplier ** ($attempt - 1));
        $jittered = $base * (0.5 + random_int(0, 1000) / 1000 / 2);

        return min($jittered, $retry->maxDelayMs, $cap * 1000) / 1000;
    }

    public static function parseRetryAfter(string $value, ?float $now = null): ?float
    {
        $value = trim($value);
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }
        $date = DateTimeImmutable::createFromFormat(self::HTTP_DATE, $value, new DateTimeZone('UTC'));
        if (false === $date) {
            return null;
        }

        return max(0.0, $date->getTimestamp() - ($now ?? (float) time()));
    }
}
