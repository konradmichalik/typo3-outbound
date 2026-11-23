# Operating

## Installation

```bash
composer require konradmichalik/typo3-outbound
```

Interfaces with `#[Connection]` or `#[Resource]` below your `Services.yaml` resource are registered automatically. Interfaces outside it or in its `exclude` paths need `OutboundServices::discover()` in `Configuration/Services.php`:

```php
use KonradMichalik\Typo3Outbound\DependencyInjection\OutboundServices;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator, ContainerBuilder $builder): void {
    OutboundServices::discover($builder, __DIR__.'/../Classes/Api');
};
```

Invalid declarations fail the container build.

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

CLI, Scheduler and Messenger have no current site. Select one with `--scope=main` (the site identifier) on `outbound:call`, or in your own code with `withSite()` of the injected `RequestContext`:

```php
$contact = $this->requestContext->withSite('main', fn () => $this->contacts->find(42));
```

## Commands

| Command | Purpose |
|---------|---------|
| `outbound:debug [connection] [--format=json]` | endpoints with effective configuration and origin |
| `outbound:lint [--strict]` | likely mistakes the build accepts |
| `outbound:call Key name=value [--scope] [--dry-run]` | call an endpoint or show the redacted request; unknown argument names fail |
| `outbound:health` | run `#[HealthCheck]` endpoints, report deprecations; exit code 1 on failure |
| `outbound:cache:flush connection` | flush cached responses and pages of a connection |
| `outbound:make:dto Class --from=sample.json --namespace=... --output=...` | DTO scaffolds from a sample |
| `outbound:generate --from=openapi.yaml --name=X --namespace=... --output=...` | interfaces and DTOs from OpenAPI |

Each command explains its arguments, examples and exit codes with `--help`, e.g. `vendor/bin/typo3 outbound:call --help`.

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
