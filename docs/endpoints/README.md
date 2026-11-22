# Defining endpoints

## Connection and resources

A connection is an interface carrying `#[Connection]`: everything that belongs to the API account (base URI, authentication, limits, format, safety settings). Resource interfaces extend it and carry `#[Resource]` with their path prefix. A small API declares its endpoints on the connection interface directly; once resources exist, the connection interface has no endpoints of its own (`InvalidDefinition` otherwise).

Values from the environment are value objects, checked by IDE and PHPStan:

| Value | Resolves to |
|-------|-------------|
| `new Env('NAME')` | an environment variable |
| `new SiteSetting('crm.baseUri')` | a setting of the current site |
| `new Secret('crm.token')` | a site setting holding `env:NAME`, resolved from that environment variable; a plain value is rejected |

A base URI or OAuth token URL from site settings needs `allowedHosts`; without it the container build fails, because whoever can edit the setting could redirect calls and credentials to any host. For a base URI from an environment variable `outbound:lint` warns.

Values that would misbehave at runtime also fail the container build: a `#[Cache]` lifetime below 1 (the database backend treats 0 as "never expires"), a non-positive `#[Timeout]`, a `#[Retry]` with a negative `maxRetries`, `delayMs` or `maxDelayMs`, a multiplier below 1 or status codes outside 100 to 599, a `#[Paginate]` size or `maxPages` below 1 or the `Cursor` style without `cursorPath`, a `#[CircuitBreaker]` threshold or cooldown below 1, a `#[RateLimit]` interval that is not a positive duration, and path braces that are not a `{name}` placeholder.

## Endpoints and parameters

One verb attribute per method: `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[QueryMethod]`. Placeholders `{name}` bind to the parameter of the same name and are always percent-encoded. Every other parameter needs a role: `#[Query]` (with `QueryStyle`), `#[Body]` (JSON or form), `#[Header]`, `#[Multipart]` or `#[Path('other')]`. `#[Sensitive]` redacts a value in logs.

Verb options: `unwrap: 'data'` for envelopes, `idempotent: true` for safe POSTs such as searches, `idempotencyKey: true` to send an Idempotency-Key reused across retries, `ifMatch: true` to send the last ETag seen for the resource.

## Return types

| Return type | Behaviour |
|-------------|-----------|
| DTO, scalar, `?DTO` | mapped; `#[OnStatus(404, returns: null)]` turns a status into a value |
| `array` with `@return list<Dto>` or `array<string, int>` | mapped by the PHPDoc type |
| `void` | status checked, body ignored |
| `RawResponse` | unmapped, for downloads and streams |
| `iterable` with `@return iterable<Dto>` and `#[Paginate]` | pages loaded lazily |
| `Deferred` with `@return Deferred<Dto>` | request sent immediately, mapped on `get()` |

## DTOs

Plain `final readonly` classes; Valinor fills them through the constructor. They need no attributes and no dependency on this extension. Superfluous response fields are ignored, missing nullable values become null. Lists need an item type in PHPDoc (`@param list<string> $tags`); Valinor's strict mode rejects untyped arrays and `ApiMappingFailed` says so.

Differences between API and DTO are declared on the interface: `keyCase: KeyCase::Camel` on the connection, `#[MapKeys(['towns.*.label' => 'name'])]` on a method, `unwrap` on the verb.

`outbound:make:dto` scaffolds DTOs from a sample response, `outbound:generate` creates interfaces and DTOs from an OpenAPI document.

## Naming

The connection name is the base interface name without `Api`, in snake_case (`CrmApi` -> `crm`), overridable with `name:`. Endpoint keys are `Interface::method`. Both appear in logs, cache tags (`outbound_crm`), commands and mocks.
