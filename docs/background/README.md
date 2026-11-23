# Background

## Architecture

The compiler pass reads all API interfaces once and bakes their metadata into the container. A proxy generated on first use hands every call to the runtime:

`EndpointInvoker` → `CallFactory` (request, credentials) → `CacheLayer` (serve, revalidate) → `Dispatcher` (breaker, rate limit, budget, retries, size limit) → `ResultMapper` (typed return value)

## Security model

- HTTPS only; plain HTTP and private addresses only with `allowPrivateNetwork`, meant for intranet APIs
- Every URL, next-page links included, must match the allowed hosts; ports other than 80 and 443 must be listed as `host:port` or be the port of a base URI from code or an environment variable, never one from site settings
- URLs from site settings (base URI, token or login URL) need `allowedHosts`, enforced when the container is built
- Redirects are off: a 3xx answer throws `ApiRedirected` naming the target; the response size is limited during and after the download
- Path values are percent-encoded, dot segments are rejected, and generated proxy code contains no attribute values
- Secrets come from environment variables via `Secret`, never from site settings
- Logs and exception messages mask credentials and sensitive query or header names; error bodies and argument values are never logged
- TLS verification can only be switched off in the Development context; cached entries are raw JSON and never unserialized into objects

## Performance

The runtime adds about 0.18 ms per uncached call and 0.12 ms per cache hit (`OverheadTest`, mock transport). In a TYPO3 instance, `ddev benchmark` measures the Petstore plugin with fixture responses (50 requests, median):

| Scenario | TYPO3 13 | TYPO3 14 |
|----------|----------|----------|
| Page cache hit | 5 queries | 5 queries |
| Response cache hit, uncached page | 10.1 ms, 6 queries | 15.4 ms, 7 queries |
| Response cache flushed before each request | 11.6 ms, 12 queries | 16.8 ms, 13 queries |

A response cache hit costs one query; a miss adds about 1.5 ms and six queries, the network call excluded. Times start at the profiler middleware, without the TYPO3 bootstrap. Shared state is read sparingly, because every access is a cache backend query: the circuit breaker once per call, announced quotas at most every five seconds, an OAuth token once per process.

## Comparison

Saloon offers a mature ecosystem with request classes. typo3-outbound trades that for one method per endpoint, typed mapping without manual DTO code, web-safe retry defaults and TYPO3 integration: site credentials, caching framework and page cache. Symfony HttpClient provides the transport.
