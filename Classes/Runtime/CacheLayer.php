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

use KonradMichalik\Typo3Outbound\Cache\{CacheEntry, CacheInvalidator, CacheKey, CacheLock, CacheTagReporter, CacheTags, ResponseCache};
use KonradMichalik\Typo3Outbound\Context\CallContext;
use KonradMichalik\Typo3Outbound\Exception\{ApiRateLimited, ApiTransportFailed, ApiUnavailable};
use KonradMichalik\Typo3Outbound\Metadata\{ParameterKind, ReturnKind};
use KonradMichalik\Typo3Outbound\Resilience\StateStore;
use KonradMichalik\Typo3Outbound\Support\Clock;
use Psr\Log\LoggerInterface;
use Throwable;
use WeakReference;

use function array_flip;
use function array_intersect_key;
use function hash;
use function is_iterable;
use function is_string;
use function json_encode;
use function ksort;
use function max;
use function min;
use function strtolower;

/**
 * CacheLayer.
 *
 * Wraps the dispatcher for endpoints with #[Cache]:
 *
 * - A fresh entry skips the network and reports its remaining lifetime to the page cache.
 * - A miss reads the cache once. Further calls for the same key in this process
 *   follow the first one (coalescing), so parallel Deferred calls stay parallel
 *   and still send one request. Other processes are held off by a per-key lock;
 *   a caller that finds the lock taken serves a usable stale entry instead of waiting.
 * - Expired entries with an ETag are revalidated with If-None-Match against the
 *   entry read in begin(); `staleOnError` serves an expired entry when the API fails.
 *
 * Successful write endpoints with #[InvalidatesCache] flush their tags.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class CacheLayer
{
    private const STALE_PAGE_LIFETIME = 10;

    private const STORED_HEADERS = ['link', 'content-type', 'etag'];

    /** @var array<string, WeakReference<Flight>> */
    private array $flights = [];

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly ResponseCache $cache,
        private readonly CacheTagReporter $reporter,
        private readonly CacheInvalidator $invalidator,
        private readonly CacheLock $lock,
        private readonly RequestBudget $budget,
        private readonly StateStore $store,
        private readonly CallContext $context,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {}

    public function begin(Execution $execution): Pending
    {
        if (!$this->cacheable($execution)) {
            return $this->dispatcher->start($execution);
        }
        $now = (int) $this->clock->now();
        $key = $this->key($execution);
        $entry = $this->cache->get($key);
        if (null !== $entry && $entry->isFresh($now)) {
            return $this->served($execution, $entry, $entry->freshUntil - $now);
        }

        $flight = ($this->flights[$key] ?? null)?->get();
        if (null !== $flight && !$flight->isDone()) {
            return new Pending($execution, null, $this->clock->now(), entry: $entry, flight: $flight);
        }

        $staleUsable = null !== $entry && $entry->isUsableWhenStale($now);
        $lock = $this->lock->acquire($key, $staleUsable ? 0.0 : $this->budget->lockWait());
        if (null === $lock && $staleUsable) {
            $this->logger->debug('{endpoint}: another process refreshes the entry; serving it stale meanwhile.', ['endpoint' => $execution->endpoint->key]);

            return $this->served($execution, $entry, $this->staleLifetime($entry, $now));
        }
        if (null !== $lock && $lock->waited) {
            // Another process held the lock and has probably just stored a fresh entry.
            $entry = $this->cache->get($key);
            $now = (int) $this->clock->now();
            if (null !== $entry && $entry->isFresh($now)) {
                $lock->release();

                return $this->served($execution, $entry, $entry->freshUntil - $now);
            }
        }
        if (null !== $entry?->etag) {
            $execution->request = $execution->request->withHeaders(['If-None-Match' => $entry->etag]);
        }

        try {
            $started = $this->dispatcher->start($execution);
        } catch (ApiUnavailable|ApiRateLimited|ApiTransportFailed $exception) {
            $lock?->release();
            if (null !== $entry && $entry->isUsableWhenStale($now)) {
                return $this->stale($entry, $execution, $exception->getMessage());
            }

            throw $exception;
        } catch (Throwable $exception) {
            $lock?->release();

            throw $exception;
        }

        $flight = new Flight();
        $pending = new Pending($execution, $started->response, $started->startedAt, entry: $entry, flight: $flight, leader: true, lock: $lock);
        $flight->leader = $pending;
        $this->flights[$key] = WeakReference::create($flight);

        return $pending;
    }

    public function finish(Pending $pending): Result
    {
        $execution = $pending->execution;
        if (null !== $pending->cached) {
            $this->report($execution, $pending->cached, $pending->reportLifetime);

            return $pending->cached;
        }
        if (null !== $pending->flight) {
            return $this->finishFlight($pending, $pending->flight);
        }

        $result = $this->dispatcher->wait($pending);
        $this->rememberEtag($execution, $result);
        $this->invalidate($execution, $result);

        return $result;
    }

    private function finishFlight(Pending $pending, Flight $flight): Result
    {
        if (!$flight->isDone()) {
            $leader = $flight->leader ?? $pending;
            try {
                $flight->result = $this->complete($leader);
            } catch (Throwable $exception) {
                $flight->error = $exception;
            } finally {
                $leader->lock?->release();
                unset($this->flights[$this->key($leader->execution)]);
            }
        }
        $result = $flight->outcome();
        if (!$pending->leader) {
            $this->report($pending->execution, $result, $pending->execution->endpoint->cache->lifetime ?? 0);
        }

        return $result;
    }

    private function complete(Pending $pending): Result
    {
        $execution = $pending->execution;
        $entry = $pending->entry;
        $now = (int) $this->clock->now();
        try {
            $result = $this->dispatcher->wait($pending);
        } catch (ApiUnavailable|ApiTransportFailed $exception) {
            if (null !== $entry && $entry->isUsableWhenStale($now)) {
                return $this->servedStale($entry, $execution, $exception->getMessage());
            }

            throw $exception;
        }
        $this->rememberEtag($execution, $result);
        $this->invalidate($execution, $result);
        $cache = $execution->endpoint->cache;
        if (null === $cache) {
            return $result;
        }
        $key = $this->key($execution);

        if (304 === $result->status && null !== $entry) {
            $renewed = $entry->renewed($now, $cache->lifetime, $cache->staleOnError);
            $this->cache->set($key, $renewed, $cache->lifetime + $cache->staleOnError, $this->tags($execution));
            $result = $this->fromEntry($renewed, $execution);
            $this->report($execution, $result, $cache->lifetime);

            return $result;
        }
        if (($result->status >= 500 || 429 === $result->status) && null !== $entry && $entry->isUsableWhenStale($now)) {
            return $this->servedStale($entry, $execution, 'status '.$result->status);
        }
        if ($result->isSuccessful()) {
            $stored = new CacheEntry(
                $result->status,
                $result->body,
                array_intersect_key($result->headers, array_flip(self::STORED_HEADERS)),
                $result->header('etag'),
                $now + $cache->lifetime,
                $now + $cache->lifetime + $cache->staleOnError,
            );
            $this->cache->set($key, $stored, $cache->lifetime + $cache->staleOnError, $this->tags($execution));
            $this->report($execution, $result, $cache->lifetime);
        }

        return $result;
    }

    private function cacheable(Execution $execution): bool
    {
        return null !== $execution->endpoint->cache
            && $execution->endpoint->idempotent
            && ReturnKind::Raw !== $execution->endpoint->returnType->kind
            && !is_iterable($execution->request->body);
    }

    private function key(Execution $execution): string
    {
        return CacheKey::for($execution->endpoint->key, $execution->request->url, $execution->identity, $this->context->language(), $this->variant($execution));
    }

    /**
     * What else shapes the response besides URL and credentials: the body and the headers
     * set by #[Header] parameters. Without it two callers would share one entry.
     */
    private function variant(Execution $execution): string
    {
        $headers = [];
        foreach ($execution->endpoint->parametersOf(ParameterKind::Header) as $parameter) {
            $headers[strtolower($parameter->wireName)] = $execution->request->headers[$parameter->wireName] ?? '';
        }
        $body = is_string($execution->request->body) ? $execution->request->body : '';
        if ([] === $headers && '' === $body) {
            return '';
        }
        ksort($headers);

        return hash('sha256', $execution->request->method."\n".$body."\n".json_encode($headers, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    private function tags(Execution $execution): array
    {
        return CacheTags::render($execution->connection->name, $execution->endpoint->cache->tags ?? [], $execution->arguments);
    }

    private function report(Execution $execution, Result $result, int $lifetime): void
    {
        if (null === $execution->endpoint->cache || !$result->isSuccessful()) {
            return;
        }
        $this->reporter->report($this->tags($execution), max(1, $lifetime));
    }

    private function invalidate(Execution $execution, Result $result): void
    {
        $invalidates = $execution->endpoint->invalidates;
        if (null === $invalidates || !$result->isSuccessful()) {
            return;
        }
        $tags = [];
        foreach ($invalidates->tags as $template) {
            $tags[] = CacheTags::tag($template, $execution->arguments);
        }
        $this->invalidator->invalidate($tags);
    }

    /**
     * A stale entry stands in for data that could not be refreshed. The page cache must not
     * keep it for the whole stale window, or the page stays old long after the API is back.
     */
    private function staleLifetime(CacheEntry $entry, int $now): int
    {
        return min($entry->staleUntil - $now, self::STALE_PAGE_LIFETIME);
    }

    private function served(Execution $execution, CacheEntry $entry, int $lifetime): Pending
    {
        return new Pending($execution, null, $this->clock->now(), $this->fromEntry($entry, $execution), $entry, reportLifetime: $lifetime);
    }

    private function fromEntry(CacheEntry $entry, Execution $execution): Result
    {
        return new Result($entry->status, $entry->headers, $entry->body, $execution->request->url, 0, true);
    }

    private function stale(CacheEntry $entry, Execution $execution, string $reason): Pending
    {
        $this->logger->warning('{endpoint} failed ({reason}); serving a stale cache entry.', ['endpoint' => $execution->endpoint->key, 'reason' => $reason]);

        return $this->served($execution, $entry, $this->staleLifetime($entry, (int) $this->clock->now()));
    }

    private function servedStale(CacheEntry $entry, Execution $execution, string $reason): Result
    {
        $pending = $this->stale($entry, $execution, $reason);
        $result = $pending->cached ?? $this->fromEntry($entry, $execution);
        $this->report($execution, $result, $pending->reportLifetime);

        return $result;
    }

    private function rememberEtag(Execution $execution, Result $result): void
    {
        if (!$execution->connection->tracksEtags) {
            return;
        }
        $etag = $result->header('etag');
        if (null !== $etag && $result->isSuccessful() && 'GET' === $execution->request->method) {
            $this->store->set(EtagMemory::key($execution->connection->name, $execution->request->url), ['etag' => $etag], EtagMemory::TTL);
        }
    }
}
