# Background

## Architecture

The compiler pass reflects all registered interfaces once and stores their metadata per interface in the compiled container. A proxy generated on first use delegates every method to `EndpointInvoker`: `CallFactory` builds the request (credentials, idempotency key, If-Match), `CacheLayer` serves or revalidates cached entries, `Dispatcher` sends with circuit breaker, rate limit, time budget, retries and size limit, `ResultMapper` turns the result into the declared return value. Creating a proxy builds no HTTP client.

## Security model

- HTTPS only; plain HTTP only with `allowPrivateNetwork`, which is meant for intranet APIs
- Every URL, including next-page links from responses, must match the allowed hosts; a port other than 80 or 443 must be the port of the base URI or appear in an entry like `api.example.com:8443`, and such an entry allows only that port
- `NoPrivateNetworkHttpClient` blocks private addresses (it cannot verify targets behind an HTTP proxy; `outbound:lint` warns)
- Redirects are disabled; response size is limited during and after download
- Path placeholders are percent-encoded and dot segments (`.`, `..`) are rejected; generated proxy code contains no attribute values
- URLs from site settings (base URI, OAuth token URL) must match `allowedHosts`, enforced when the container is built
- Secrets come from environment variables via `Secret`, never from site settings; logs and exception messages are redacted, including URLs quoted in transport errors (the chained original exception is replaced by a redacted copy) and credentials in the URL authority; query keys and header names are masked by pattern (anything containing `token`, `secret`, `auth`, `passw`, `api_key`, `jwt`, `signature`, `credential`, `cookie` or `session`, also in array form like `token[]`), plus those declared with `#[Sensitive]` or as API key name, and static connection headers from `Env` or `Secret`; error text taken from API responses is shortened to one line
- TLS verification can only be switched off in the Development context
- Cache entries are raw JSON; nothing from a cache is unserialized into objects
- Outside TYPO3, generated proxies live in a per-user directory created with mode 0700; a directory writable by group or others is refused

## Performance

Measured with `Tests/Unit/Performance/OverheadTest` (PHP 8.3, mock transport, no network): about 0.18 ms per uncached call including request building, decoding and mapping, about 0.12 ms per cache hit. Reproduce with `OUTBOUND_BENCH_REPORT=1 composer test -- --filter OverheadTest`.

In a TYPO3 instance, `ddev benchmark` measures the Petstore plugin with fixture responses and [typo3-request-profiler](https://github.com/konradmichalik/typo3-request-profiler) (50 requests, median, DDEV on Apple silicon):

| Scenario | TYPO3 13 | TYPO3 14 |
|----------|----------|----------|
| Page cache hit | 5 queries | 5 queries |
| Response cache hit, uncached page | 10.1 ms, 6 queries | 15.4 ms, 7 queries |
| Response cache flushed before each request | 11.6 ms, 12 queries | 16.8 ms, 13 queries |

Of the queries on an uncached page, a response cache hit adds one on `cache_outbound`; the others are TYPO3 rendering the page. A miss adds about 1.5 ms and six queries for the state store reads and the cache write, with the network call itself excluded. Times start at the profiler middleware, so the TYPO3 bootstrap is not included, and a page cache hit is answered almost entirely before it.

Shared state is touched as little as possible, because in TYPO3 every access is a cache backend query. A successful call reads the circuit breaker once (if the connection has one); the announced quota is read at most every five seconds per process, an OAuth token once per process until it expires, and ETags are only stored for connections with an `ifMatch` endpoint. `Tests/Unit/Performance/BackendAccessTest` pins this: 4 store accesses for three calls, previously 12.

Valinor's compiled type definitions are cached under `var/cache/code/outbound_valinor` (first mapping of a request about 21 ms instead of 33 ms in the measurement); in the Development context the cache watches the DTO files.

Deliberately not optimized, because measurements or call counts showed no gain: reusing the Valinor mapper instance (0.10 vs. 0.11 ms per call), an index for endpoint lookup, memoizing resolved values, reusing the cache key within a call, and skipping the two `stat` calls per proxy.

## Comparison

Saloon offers a mature ecosystem with request classes. typo3-outbound trades that for one method per endpoint, typed mapping without manual DTO code, web-safe retry defaults and TYPO3 integration (site credentials, caching framework, page cache). Symfony HttpClient provides the transport, `NoPrivateNetworkHttpClient` and the mock classes.

## Deviations from the concept

- Discovery reuses the `Services.yaml` resource scan: Symfony keeps scanned interfaces as abstract `container.excluded` definitions until its removing passes, and `OutboundCompilerPass` turns those with outbound attributes into services. Exclude paths are skipped, because their classes may not load. `OutboundServices::discover()` covers interfaces outside a resource or in an exclude path
- A site is selected outside requests with `RequestContext::withSite()` instead of `$client->forSite()`, so interfaces stay free of framework methods
- The comparison spike with Saloon (step 0) was skipped on request
