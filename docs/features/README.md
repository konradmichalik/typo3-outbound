# Features

## Timeouts and retries

Each attempt has a total duration (Symfony `max_duration`): 3 s in web requests, 30 s in CLI, overridable with `#[Timeout]`. All calls of one web request share a time budget (8 s). `#[Retry]` uses exponential backoff with jitter; a `Retry-After` above the context's cap (1 s web, 60 s CLI) gives up instead of blocking. Non-idempotent requests are never retried unless `force: true` or `idempotencyKey: true`. After a 401 an OAuth token is refreshed once.

## Caching

`#[Cache(lifetime, tags, staleOnError)]` on idempotent endpoints. On a connection or resource it is the default for the reading endpoints there (GET, HEAD, OPTIONS, QUERY); writes, PUT and DELETE included, stay uncached. Keys include site, language, principal, request body and `#[Header]` parameters. Endpoints with a multipart body are never cached. Bodies are cached as raw JSON, never as serialized objects.

- **Page cache:** tags and the *remaining* lifetime go to the page cache (a hit 45 s into a 60 s entry reports 15 s), so `outbound:cache:flush crm` (or `cache:flushtags outbound_crm` on TYPO3 14) clears data and pages together.
- **Misses:** one cache read per miss. Calls for the same key within one request are coalesced into one API call, parallel `Deferred` calls stay parallel. Other processes wait for a per-key lock (at most 2 s in web requests, capped by the time budget) and use the entry the lock holder stored; if a usable stale entry exists, it is served instead of waiting.
- **HTTP semantics:** expired entries with an ETag are revalidated with `If-None-Match` against the entry read before the request; with `staleOnError` an expired entry is served while the API fails (exceptions, 5xx, 429). A page showing such a stale entry may be cached for 10 seconds at most, so it refreshes soon after the API is back.

### Tags

Templates like `crm_contact_{id}` are validated when the container is built: every placeholder must be a non-nullable `int`, `string` or backed enum parameter, the static part a valid TYPO3 cache tag. Values outside `[A-Za-z0-9_-]` (or longer than 64 characters) are hashed (`h` + 16 hex characters), so `a.b` and `a_b` never share a tag. Use `CacheTags::tag('crm_contact_{id}', ['id' => 42])` in your own invalidation code, e.g. a DataHandler hook, to produce exactly the tag the cache set.

### Invalidation

`#[InvalidatesCache(tags: ['crm_contact_{id}', 'crm_contacts'])]` on write endpoints flushes the tags after a 2xx response, never after errors. In TYPO3 the flush goes through `CacheManager::flushCachesByTags()`, so pages showing the data are cleared as well. Tags are not scoped to a site. With a `Deferred` return type the flush happens on `get()`. `outbound:lint` warns about tags that no `#[Cache]` sets.

## Rate limits and circuit breaker

`#[RateLimit(100, per: '1 minute')]` applies to the whole connection. In web requests an exhausted limit throws `ApiRateLimited`; CLI waits. A quota announced by the API (`X-RateLimit-Remaining`/`-Reset`, configurable) is honoured the same way. `#[CircuitBreaker]` opens after consecutive failures; while open, calls fail with `ApiUnavailable` without touching the network.

The rate limit, the announced quota and the circuit breaker are shared by all sites as long as the connection has one base URI. With a base URI from a site setting every site talks to its own API, so each site keeps its own state.

## Pagination

`#[Paginate(style: ...)]` with `Page`, `Offset`, `Cursor` (`cursorPath`), `LinkHeader` (RFC 8288) or `Format` (next link from JSON:API/Hydra). `maxPages` bounds the requests. Next-page links pass the host guard like every other URL.

With `Page` and `Offset`, only an empty page ends the iteration, because servers often cap the page size without saying so; a short page is no proof of the end. This costs one request for the empty page after the last items. An unchanged cursor or a next link to the page just loaded also ends it, instead of repeating that page until `maxPages`.

## Formats

`format: new PlainJson()` (default, RFC 9457 problem details), `new JsonApi(includeDepth: 3)` (flattening, `included` resolution with a depth limit) or `new Hydra(prefix: false)` (API Platform 4 default; both prefix variants are read). Custom formats implement `ResponseFormat`.

## Deferred calls

`@return Deferred<PetDto>` starts the request immediately; several deferred calls run in parallel until `get()` is called.

## API conventions

`Accept-Language` from the site language, `Idempotency-Key` (quoted by default, configurable), `If-Match`, `Sunset` and `Deprecation` headers logged and reported by `outbound:health`, multipart uploads with `FilePart`.
