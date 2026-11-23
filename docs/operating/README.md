# Operating

## Installation

```bash
composer require konradmichalik/typo3-outbound
```

Interfaces with `#[Connection]` or `#[Resource]` below your `Services.yaml` resource are registered automatically. Interfaces outside it or in its `exclude` paths need `OutboundServices::discover()` in `Configuration/Services.php`. Invalid declarations fail the container build.

## Configuration

Extension configuration holds global defaults only: `webTimeout`, `cliTimeout`, `webBudget`, `webRetryAfterCap`, `cliRetryAfterCap`, `maxResponseBytes`. Values of a connection live on its interface and in environment variables or site settings.

## Caches

| Cache | Content | Group |
|-------|---------|-------|
| `outbound` | response bodies | pages, all |
| `outbound_state` | breaker, quotas, tokens, ETags, rate limiter | system |
| `outbound_proxies` | generated proxy code | system |

Use a shared backend such as Redis for `outbound_state` on multi-server setups. The miss lock uses TYPO3's locking API; its file and semaphore strategies only coordinate processes on one server.

## Site context outside requests

CLI, Scheduler and Messenger have no current site. Select one explicitly: `RequestContext::withSite('main', fn () => ...)` or `--site=main` on the commands.

## Commands

| Command | Purpose |
|---------|---------|
| `outbound:debug [connection] [--format=json]` | endpoints with effective configuration and origin |
| `outbound:lint [--strict]` | likely mistakes the build accepts |
| `outbound:call Key name=value [--site] [--dry-run]` | call an endpoint or show the redacted request |
| `outbound:health` | run `#[HealthCheck]` endpoints, report deprecations; exit code 1 on failure |
| `outbound:cache:flush connection` | flush cached responses and pages of a connection |
| `outbound:make:dto Class --from=sample.json` | DTO scaffolds from a sample |
| `outbound:generate --from=openapi.yaml --name=X --namespace=... --output=...` | interfaces and DTOs from OpenAPI |

## Testing

`MockTransport` answers by endpoint and arguments, never by URL; unexpected calls fail.

In functional tests, `OutboundTestTrait` routes all outbound calls to a `MockTransport`, including those of frontend subrequests:

```php
final class ContactControllerTest extends FunctionalTestCase
{
    use OutboundTestTrait;

    #[Test]
    public function showsContact(): void
    {
        $this->mockEndpoint(ContactsApi::class, 'find', ['id' => 42])->respondWithFixture(__DIR__.'/contact.json');
        // execute a frontend request
        $this->assertEndpointCalled(ContactsApi::class, 'find');
    }
}
```

The mock is removed after each test. In unit tests, `Outbound::create([...])->withTransport($mock)->api(ContactsApi::class)` composes the runtime without a container.

## Local development with DDEV

The repository uses [ddev-typo3-multi-version-extension](https://github.com/konradmichalik/ddev-typo3-multi-version-extension) 0.5.0:

```bash
ddev start
ddev install all                 # TYPO3 13 and 14 under .Build/<version>
ddev launch                      # overview page
ddev 14 typo3 outbound:debug     # TYPO3 CLI of one instance
```

`ddev install` installs the fixture extension `outbound_petstore` as sitepackage and imports `Tests/Acceptance/Fixtures/sites/main`, whose site set renders the Petstore plugin on https://13.typo3-outbound.ddev.site and https://14.typo3-outbound.ddev.site. Set `OUTBOUND_PETSTORE_MOCK=1` in `.ddev/docker-compose.zz-project.yaml` to answer from the fixture responses. Update the add-on with `ddev add-on get konradmichalik/ddev-typo3-multi-version-extension`; `config.yaml`, `docker-compose.zz-project.yaml`, `.setup/project.sh` and `.setup/hooks/` are repo-owned and survive updates. The `post-typo3-setup` hook removes the page TypoScript that `typo3 setup` creates, because it would hide the TypoScript of the Petstore site set.

## Petstore demo

`composer demo` serves `Tests/Demo/public` on http://localhost:8080 against petstore3.swagger.io; `?mock=1` answers from the fixture responses. Each page lists the requests it sent, so retries and cache hits are visible. In TYPO3 the same frontend is the Extbase plugin of the fixture extension `outbound_petstore` (see DDEV above). The extension is self-contained: Petstore interfaces, DTOs, fixture responses and the mock transport live in it, so it also works when installed as a package.
