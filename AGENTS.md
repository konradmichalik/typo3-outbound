# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

TYPO3 extension `typo3_outbound` (`konradmichalik/typo3-outbound`): the TYPO3 integration of the framework-agnostic core [`konradmichalik/php-outbound`](https://github.com/konradmichalik/php-outbound) (namespace `KonradMichalik\Outbound\`, MIT, own repository). External APIs are declared as PHP interfaces with attributes; the core generates proxies and runs the resilience, caching and safety runtime, this extension wires it into TYPO3.

- Extension key: `typo3_outbound`
- Namespace: `KonradMichalik\Typo3Outbound\`
- PHP: `~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0`
- TYPO3: `^13.4 || ^14.0`
- Core dependency: `konradmichalik/php-outbound` with a caret on `0.x`, so every core minor is an explicit opt-in. A change that touches both packages needs a core release first

## Structure

- `Classes/`: adapters to TYPO3, each implementing an interface of the core, grouped like the core's directories:
  - `Cache/`: `CacheResponseCache`, `CacheManagerInvalidator`, `LockFactoryCacheLock`, `PageCacheTagReporter`
  - `Resilience/`: `CacheStateStore`, `RateLimiterStorage`
  - `Context/`: `RequestContext` (call context and scope runner), `Typo3ExecutionContext`
  - `Proxy/`: `PhpCacheProxyStore`
  - `Mapping/`: `ValinorCacheFactory`
  - `Configuration/`: `Configuration` (cache registration), `SettingsFactory` (extension configuration)
  - `Middleware/RequestContextMiddleware`: hands the current request to `RequestContext`
- `Configuration/Services.php`: registers the core services (its location comes from the autoloader, because it differs between Composer and the vendor bundle of classic mode) and the compiler pass; loaded before `Services.yaml`, whose explicit definitions override
- `Configuration/Services.yaml`: wiring of the TYPO3 adapters, caches and console commands
- `Tests/Functional/Fixtures/Extensions/outbound_petstore/`: self-contained fixture extension: Petstore interfaces (`KonradMichalik\OutboundPetstore\Api`), DTOs, responses, mock transport and an Extbase plugin as TYPO3 frontend; installed as sitepackage by DDEV. `Api`, `Dto`, `Transport` and `Resources/Private/Responses` are a derived copy of `tests/src/Fixtures/Petstore` in the core, because `Tests/` is not part of the Composer dist
- `Tests/Acceptance/Fixtures/`: DDEV install fixtures (site config with the Petstore site set)

Gotchas:
- The core is bundled into the TER artefact by `typo3-vendor-bundler` (`autoload.enabled: true`) and must be checked after changing its requirements
- `Services.php` loads before `Services.yaml`; definitions in `Services.yaml` for a core class replace the ones from the resource scan

## Development commands

Requires [DDEV](https://ddev.readthedocs.io/en/stable/) with the add-on [ddev-typo3-multi-version-extension](https://github.com/konradmichalik/ddev-typo3-multi-version-extension) (0.5.0, files under `.ddev/` marked `#ddev-generated` are managed by it).

```bash
ddev start
ddev install all              # TYPO3 13 and 14 under .Build/<version>
ddev 14 typo3 outbound:debug  # TYPO3 CLI in one instance
```

Quality tools live in `Tests/CGL/`, invoked via `composer cgl` (lint, fix, sca, migration). PHPStan runs on level max; `Tests/CGL/phpstan-baseline.neon` holds the cognitive complexity findings of `Configuration.php`. `composer cgl sca` also runs the dead code detector with the TYPO3 entry points of `konradmichalik/phpstan-typo3-dead-code`; it needs `--autoload-file=../../vendor/autoload.php`, which the script already passes.

## Testing

```bash
composer test             # unit tests of the adapters
composer test:functional  # functional tests, need a database
Build/verify.sh           # all checks needing DDEV, a database or network, with summary
```

- Exception codes of the `1794700xxx` range are shared with the core; `ExceptionCodeUniquenessTest` checks the extension and the installed core together
- Global state in tests (`$GLOBALS`, `Environment`, env vars) is set with the attributes of [`konradmichalik/ttt`](https://github.com/konradmichalik/ttt) (`#[WithGlobal]`, `#[WithEnvironment]`, ...), registered in both `phpunit*.xml`, instead of `setUp()`/`tearDown()` or separate processes
- `declare(strict_types=1)` everywhere, `final readonly` where sensible

## Git workflow

- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
