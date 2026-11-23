# Implementation checklist

Derived from the concept document and the repository setup of `konradmichalik/typo3-routing`.
Scope: everything up to and including v1. Every ticked item is implemented and tested in this repository; the last section lists what this environment cannot verify.

## 1. Repository setup (mirrors typo3-routing)

- [x] `composer.json`: GPL-2.0-or-later, tab indentation, platform PHP 8.2, PSR-4 `Classes/` and `Tests/`, scripts `test`, `test:unit`, `test:coverage`, `test:functional`, `cgl`
- [x] `ext_emconf.php`, `ext_localconf.php`, `ext_conf_template.txt` (global defaults only)
- [x] `Configuration/Services.php` (compiler pass), `Services.yaml`, `RequestMiddlewares.php`
- [x] `AGENTS.md`, `CLAUDE.md`, `README.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `CODEOWNERS`, `LICENSE.md`
- [x] `.editorconfig`, `.gitattributes` (export-ignore), `.gitignore`, `packaging_exclude.php`, `renovate.json`, `version-bumper.yaml`, `composer-unused.php`
- [x] `Tests/CGL/` isolated Composer project, copied from typo3-routing (not executed here: needs Packagist)
- [x] `.github/workflows` and issue templates, copied from typo3-routing (not executed)
- [x] `.ddev` setup with ddev-typo3-multi-version-extension 0.5.0: TYPO3 13 and 14, fixture extension `outbound_petstore` as sitepackage, site config with the Petstore site set (not started here: no Docker)
- [x] `phpunit.xml`, `phpunit.functional.xml`
- [x] `docs/` in four groups: defining endpoints, features, operating, background
- [x] Header doc block and class doc block on every file, `declare(strict_types=1)`, `final readonly` where sensible
- [x] Exception codes unique (`ExceptionCodeUniquenessTest`)

## 2. MVP

- [x] Attributes: `#[Connection]`, `#[Resource]`, verbs (`Get`, `Post`, `Put`, `Patch`, `Delete`, `QueryMethod`), `#[Query]`, `#[Body]`, `#[Header]`, `#[Path]`, `#[Sensitive]`
- [x] Value objects `Env`, `SiteSetting`, `Secret`; `Decimal`, `RawResponse`
- [x] Metadata factory: resolves `extends` chain, precedence method > resource > connection, validation with clear errors
- [x] Naming: connection name derived from the base interface, endpoint key `Interface::method`
- [x] Discovery: from the `Services.yaml` resource scan, `OutboundServices::discover()` as fallback, compiler pass, metadata baked into the container
- [x] Proxy generator (no string interpolation of attribute values), proxies cached as PHP code, lazy pipeline
- [x] Request builder: encoded path placeholders, query styles, JSON/form body, headers
- [x] Mapping with Valinor: superfluous keys allowed, key case conversion, `#[MapKeys]`, unwrap, big integers as strings
- [x] Typed exceptions incl. `ApiConflict`; `#[OnStatus]`
- [x] Timeouts per execution context (web/CLI) on `max_duration`, time budget per request
- [x] Retry: own policy, idempotency rule, capped `Retry-After`, `force`
- [x] Auth: Bearer, Basic, API key
- [x] Security: `https` only, `allowedHosts`, `NoPrivateNetworkHttpClient` with opt-out, redirects off, `maxResponseBytes`, TLS verification only switchable in Development
- [x] Logging with redaction (auth headers, sensitive query keys, `#[Sensitive]`), default level warning
- [x] Plain JSON format with `application/problem+json` errors
- [x] Commands `outbound:debug`, `outbound:lint`
- [x] `outbound:make:dto` from a sample response
- [x] Endpoint-based mocks: `MockTransport` (unit level)
- [x] `OutboundTestTrait` for TYPO3 functional tests; reaches frontend subrequests, verified by `PetstorePluginTest`

## 3. v1

- [x] `#[Cache]` with tags, invalidation, `staleOnError`, cache key incl. site, language, auth identity
- [x] Page cache integration via `frontend.cache.collector`, tested against TYPO3's real `CacheDataCollector`
- [x] `SiteSetting`/`Secret` resolution via request context middleware (frontend and backend), explicit site in CLI
- [x] `#[RateLimit]` (fail fast in web, wait in CLI), server rate-limit headers
- [x] `#[CircuitBreaker]` (read on success, write on failure/state change)
- [x] `#[Paginate]`: page, offset, cursor, `Link` header, format links; next links checked against allowed hosts
- [x] `Deferred<T>` for parallel calls
- [x] OAuth2 client credentials with token cache
- [x] Token login with user and password (`#[Auth\TokenLogin]`), sharing the token cache
- [x] Format profiles JSON:API and Hydra, `ResponseFormat` interface
- [x] Site language as `Accept-Language`
- [x] ETags (`If-None-Match` revalidation, `If-Match` on updates)
- [x] Idempotency-Key for POST
- [x] `#[Multipart]` uploads
- [x] `Sunset`/`Deprecation` warnings
- [x] `outbound:generate` from OpenAPI (JSON and YAML)
- [x] `outbound:call` (with `--dry-run`, `--site`), `outbound:health`, `outbound:cache:flush`

## 3a. Cache review

- [x] Page cache receives the remaining lifetime of a hit, the full lifetime only for stored or renewed entries
- [x] One cache read per miss; the entry from `begin()` travels in `Pending`, so a 304 renews exactly the entry its `If-None-Match` came from
- [x] Tag templates validated at build time (placeholder type, static part against the TYPO3 tag pattern); values hashed instead of rewritten; `CacheTags::tag()` as public API
- [x] `#[InvalidatesCache]` behind `CacheInvalidator` (TYPO3: `CacheManager::flushCachesByTags()`), only after 2xx; lint warning for tags no `#[Cache]` sets
- [x] Miss handling: coalescing per key within a process, per-key `CacheLock` across processes (TYPO3: locking API) with bounded wait, stale entry instead of waiting, release on failure and on garbage collection
- [x] Counter-check: the tests for the single read and the 304 entry fail against the previous implementation

## 3b. Pilot preparation

- [x] Middleware names and the position of the frontend cache collector verified against TYPO3 14.3.11 sources
- [x] `Build/verify.sh`: all checks needing DDEV, a database or network in one run, with summary and logs
- [x] Migration guide with a typical service wrapper before and after
- [x] Wrapper audit template
- [x] Pilot evaluation template (incl. optional Saloon comparison)

## 3c. Security and performance review

Security:
- [x] Transport error messages no longer leak query credentials (Symfony quotes the full URL); credentials in the URL authority are masked too
- [x] Dot segments as path values are rejected
- [x] Base URI and OAuth token URL from site settings require `allowedHosts` (build error); the token URL is checked against them
- [x] OAuth token request has a bounded duration (was unlimited)
- [x] Error text from API responses is shortened to one line in exception messages
- [x] `outbound:call --dry-run` masks a body marked `#[Sensitive]`
- [x] Standalone proxy directory: per user, mode 0700, refused when writable by group or others

Performance:
- [x] Circuit breaker: one store read per successful call instead of two
- [x] Announced quota: read at most every five seconds per process instead of on every call
- [x] ETags: stored only for connections with an `ifMatch` endpoint instead of on every GET
- [x] OAuth token: read from the store once per process instead of on every call
- [x] Valinor file cache wired in TYPO3 (watching files in Development)
- [x] Measured: 4 instead of 12 store accesses for three calls
- [x] Rejected as over-optimization after measuring: mapper reuse, endpoint index, value memoization, cache key reuse, proxy `stat` calls

## 4. Quality

- [x] Petstore fixture: interfaces and DTOs written against the official OpenAPI document; `outbound:generate` on the same document produces an API that passes the registry validation
- [x] Petstore test frontends: standalone (`composer demo`) and Extbase plugin in the self-contained fixture extension `outbound_petstore`
- [x] 209 unit tests (data sets counted) plus 3 opt-in live tests, all green; PHPStan level max on `Classes/` with a baseline of 24 findings (codegen on untyped YAML input, `$GLOBALS` in `Configuration`)
- [x] PHP CS Fixer with rules close to the preset (the preset itself needs Packagist)
- [x] Overhead measured: ~0.18 ms per uncached call incl. mock transport, ~0.12 ms per cache hit

## 5. Not verifiable in this environment

- Comparison spike with Saloon (concept step 0): skipped on request

