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

namespace KonradMichalik\Typo3Outbound\Runtime;

use KonradMichalik\Typo3Outbound\Auth\Authenticator;
use KonradMichalik\Typo3Outbound\Exception\{ApiRateLimited, ApiTransportFailed, ApiUnavailable, BlockedRequest};
use KonradMichalik\Typo3Outbound\Resilience\{CircuitBreaker, RateLimitGuard, RetryPolicy};
use KonradMichalik\Typo3Outbound\Support\{Clock, Redactor};
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function min;
use function round;
use function sprintf;
use function str_replace;
use function strlen;

/**
 * Dispatcher.
 *
 * Sends an execution and drives it to a final response: circuit breaker, rate
 * limit, time budget, retries with capped Retry-After, one credential refresh
 * after a 401 and the response size limit. Status codes are not interpreted
 * beyond that; mapping them to exceptions is the ResultMapper's job.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Dispatcher
{
    public function __construct(
        private Transport $transport,
        private CircuitBreaker $breaker,
        private RateLimitGuard $rateLimit,
        private Authenticator $authenticator,
        private RequestBudget $budget,
        private ConventionObserver $observer,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public function start(Execution $execution): Pending
    {
        $this->breaker->guard($execution->connection, $execution->endpoint->key);
        $this->rateLimit->acquire($execution->connection, $execution->endpoint->key);

        return new Pending($execution, $this->send($execution), $this->clock->now());
    }

    public function wait(Pending $pending): Result
    {
        $execution = $pending->execution;
        $response = $pending->response ?? $this->send($execution);
        $policy = RetryPolicy::for($execution->endpoint);
        $retries = 0;

        while (true) {
            try {
                $status = $response->getStatusCode();
                $headers = $response->getHeaders(false);
            } catch (TransportExceptionInterface $exception) {
                if ($this->retry($execution, $policy, $retries + 1, null, null)) {
                    ++$retries;
                    $response = $this->send($execution);
                    continue;
                }
                $this->breaker->failure($execution->connection);

                throw new ApiTransportFailed(sprintf('%s: %s', $execution->endpoint->key, self::safeMessage($exception, $execution)), 1794700130, $execution->endpoint->key, null, null, self::redacted($exception, $execution));
            }

            $this->observer->observe($execution, $headers);

            if (401 === $status && $this->authenticator->invalidate($execution->connection) && $this->mayResend($execution) && $execution->refreshCredentials()) {
                $response->cancel();
                $response = $this->send($execution);
                continue;
            }
            if ($this->retry($execution, $policy, $retries + 1, $status, $headers['retry-after'][0] ?? null)) {
                $response->cancel();
                ++$retries;
                $response = $this->send($execution);
                continue;
            }
            break;
        }

        $body = $this->content($execution, $response, $status);
        if ($status >= 500 || 429 === $status) {
            $this->breaker->failure($execution->connection);
        } else {
            $this->breaker->success($execution->connection);
        }

        $this->logger->debug('{method} {url} answered {status} in {duration} ms after {retries} retries ({endpoint}).', [
            'method' => $execution->request->method,
            'url' => Redactor::url($execution->request->url, $execution->request->sensitiveKeys),
            'status' => $status,
            'duration' => round(($this->clock->now() - $pending->startedAt) * 1000, 1),
            'retries' => $retries,
            'endpoint' => $execution->endpoint->key,
        ]);
        if ($retries > 0 && $status >= 400) {
            $this->logger->warning('{endpoint} still answered {status} after {retries} retries.', ['endpoint' => $execution->endpoint->key, 'status' => $status, 'retries' => $retries]);
        }

        return new Result($status, $headers, $body, $execution->request->url, $retries, false, $response);
    }

    private function retry(Execution $execution, RetryPolicy $policy, int $attempt, ?int $status, ?string $retryAfter): bool
    {
        if (!$policy->allows($attempt, $status)) {
            return false;
        }
        $delay = $policy->delay($attempt, $retryAfter, min($this->budget->retryAfterCap(), $this->budget->remaining()), $this->clock->now());
        if (null === $delay || $delay >= $this->budget->remaining()) {
            return false;
        }
        $this->clock->sleep($delay);

        // Checked after the backoff: a quota or breaker may have reset while waiting.
        return $this->mayResend($execution);
    }

    /**
     * Every resent request needs a closed breaker and a rate limit token like the first one.
     * Without them the caller gets the original answer instead of a limit error hiding it.
     */
    private function mayResend(Execution $execution): bool
    {
        try {
            $this->breaker->guard($execution->connection, $execution->endpoint->key);
            $this->rateLimit->acquire($execution->connection, $execution->endpoint->key);
        } catch (ApiRateLimited|ApiUnavailable) {
            return false;
        }

        return true;
    }

    private function send(Execution $execution): ResponseInterface
    {
        $duration = $this->budget->attemptDuration($execution->endpoint->timeout?->seconds);
        if ($duration <= 0.0) {
            throw new ApiTransportFailed(sprintf('%s: the time budget of this request is exhausted.', $execution->endpoint->key), 1794700131, $execution->endpoint->key);
        }

        try {
            return $this->transport->request(new Call(
                $execution->endpoint->key,
                $execution->arguments,
                $execution->request,
                $execution->options->withMaxDuration($duration),
            ));
        } catch (TransportExceptionInterface $exception) {
            // Raised before anything was sent, e.g. by NoPrivateNetworkHttpClient for a host
            // resolving to a private address. Not a failure of the API, so the breaker stays untouched.
            throw new BlockedRequest(sprintf('%s: %s', $execution->endpoint->key, self::safeMessage($exception, $execution)), 1794700134, $execution->endpoint->key, null, null, self::redacted($exception, $execution));
        }
    }

    /**
     * Symfony's transport messages quote the full URL. Credentials sent as query
     * parameters must not reach exception messages or logs through them.
     */
    private static function safeMessage(Throwable $exception, Execution $execution): string
    {
        $url = $execution->request->url;

        return str_replace($url, Redactor::url($url, $execution->request->sensitiveKeys), $exception->getMessage());
    }

    /**
     * The original exception quotes the URL in its message and in its own chain. Debug
     * output and loggers print previous exceptions, so a redacted stand-in is chained instead.
     */
    private static function redacted(Throwable $exception, Execution $execution): RuntimeException
    {
        return new RuntimeException(sprintf('%s: %s', $exception::class, self::safeMessage($exception, $execution)), 0);
    }

    private function content(Execution $execution, ResponseInterface $response, int $status): string
    {
        if (304 === $status || 'raw' === $execution->endpoint->returnType->kind->value) {
            return '';
        }
        try {
            $body = $response->getContent(false);
        } catch (TransportExceptionInterface $exception) {
            $this->breaker->failure($execution->connection);

            throw new ApiTransportFailed(sprintf('%s: %s', $execution->endpoint->key, self::safeMessage($exception, $execution)), 1794700132, $execution->endpoint->key, $status, null, self::redacted($exception, $execution));
        }
        if (strlen($body) > $execution->options->maxResponseBytes) {
            throw new ApiTransportFailed(sprintf('%s: the response exceeds %d bytes.', $execution->endpoint->key, $execution->options->maxResponseBytes), 1794700133, $execution->endpoint->key, $status);
        }

        return $body;
    }
}
