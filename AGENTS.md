# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

TYPO3 extension `typo3_outbound` (`konradmichalik/typo3-outbound`). External APIs are declared as PHP interfaces with attributes; proxies generated from them delegate to a runtime that adds resilience, caching and safety.

- Extension key: `typo3_outbound`
- Namespace: `KonradMichalik\Typo3Outbound\`
- PHP: `~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0`
- TYPO3: `^13.4 || ^14.0`

## Structure

- `Classes/Attribute/`: `#[Connection]`, `#[Resource]`, verbs (`Get`, `Post`, ...), parameter roles, `#[Retry]`, `#[Timeout]`, `#[Cache]`, `#[RateLimit]`, `#[CircuitBreaker]`, `#[Paginate]`, `#[MapKeys]`, `#[OnStatus]`, `#[HealthCheck]`, `#[InvalidatesCache]`, `Auth\*`
- `Classes/Metadata/`: `MetadataFactory` (reflection, `extends` resolution, precedence method > resource > connection, validation), `Registry` (serialized chunks per interface), `Naming`
- `Classes/DependencyInjection/`: `OutboundCompilerPass` registers interfaces from the resource scan (`OutboundServices::discover()` as fallback), builds the registry at compile time and wires proxies
- `Classes/Proxy/`: proxy code generation and storage; generated code contains only reflection names and types, never attribute values
- `Classes/Runtime/`: `EndpointInvoker` -> `CallFactory` (request, auth, idempotency key) -> `CacheLayer` -> `Dispatcher` (breaker, rate limit, budget, retries, 401 refresh, size limit) -> `ResultMapper` / `Paginator`
- `Classes/Http/`, `Classes/Format/`, `Classes/Mapping/`, `Classes/Resilience/`, `Classes/Cache/`, `Classes/Auth/`, `Classes/Context/`, `Classes/Transport/`
- `Classes/Typo3/`: adapters to caching framework, site settings, page cache collector, extension configuration; `Classes/Middleware/RequestContextMiddleware`
- `Classes/Codegen/`, `Classes/Command/`: generators and console commands
- `Classes/Testing/`: `MockTransport`, `OutboundTestTrait`, public test API
- `Tests/Functional/Fixtures/Extensions/outbound_petstore/`: self-contained fixture extension: Petstore interfaces (`KonradMichalik\OutboundPetstore\Api`), DTOs, responses, mock transport and an Extbase plugin as TYPO3 frontend; installed as sitepackage by DDEV
- `Tests/Fixtures/Petstore/openapi.yaml`: the official Petstore OpenAPI document, used by the codegen test
- `Tests/Demo/`: standalone Petstore frontend (`composer demo`)
- `Tests/Acceptance/Fixtures/`: DDEV install fixtures (site config with the Petstore site set)

Gotchas:
- PHP does not inherit attributes; the metadata factory walks the `extends` chain itself
- The Symfony loader keeps scanned interfaces only as `.abstract.<FQCN>` definitions tagged `container.excluded` (since symfony/dependency-injection 7.4, hence the `^7.4` requirement); `findTaggedServiceIds()` hides them, so the compiler pass walks `getDefinitions()`. Classes in `exclude` paths are skipped on purpose, they may not load
- `NoPrivateNetworkHttpClient` throws synchronously and needs `symfony/http-foundation`
- Valinor runs in strict mode: list and array properties need a PHPDoc item type

## Development commands

Requires [DDEV](https://ddev.readthedocs.io/en/stable/) with the add-on [ddev-typo3-multi-version-extension](https://github.com/konradmichalik/ddev-typo3-multi-version-extension) (0.5.0, files under `.ddev/` marked `#ddev-generated` are managed by it).

```bash
ddev start
ddev install all              # TYPO3 13 and 14 under .Build/<version>
ddev 14 typo3 outbound:debug  # TYPO3 CLI in one instance
```

Quality tools live in `Tests/CGL/`, invoked via `composer cgl` (lint, fix, sca, migration). PHPStan runs on level max; `Tests/CGL/phpstan-baseline.neon` holds the codegen findings on untyped YAML input, the cognitive complexity findings and test-only `putenv`/`eval`/`exec`.

## Testing

```bash
composer test             # unit tests incl. Petstore fixture
composer test:functional  # functional tests, need a database
composer test:live        # Petstore live, needs network
Build/verify.sh           # all checks needing DDEV, a database or network, with summary
```

- Keep exception codes unique (`1794700xxx`); `ExceptionCodeUniquenessTest` fails on duplicates
- `declare(strict_types=1)` everywhere, `final readonly` where sensible

## Git workflow

- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
