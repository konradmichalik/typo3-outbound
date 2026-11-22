# Outbound

TYPO3 extension `typo3_outbound`: declarative API clients. Describe an external API as PHP interfaces with attributes; the extension generates the implementation and adds timeouts, retries, caching, rate limits, format profiles and safe defaults. The outgoing counterpart of [typo3-routing](https://github.com/konradmichalik/typo3-routing).

```php
#[Outbound\Connection(baseUri: new SiteSetting('crm.baseUri'), allowedHosts: ['crm.example.com'])]
#[Outbound\Auth\Bearer(token: new Secret('crm.token'))]
#[Outbound\Retry(maxRetries: 2)]
interface CrmApi {}

#[Outbound\Resource('/contacts')]
interface ContactsApi extends CrmApi
{
    #[Outbound\Get('/{id}')]
    #[Outbound\Cache(lifetime: 600, tags: ['crm_contact_{id}'], staleOnError: 3600)]
    #[Outbound\OnStatus(404, returns: null)]
    public function find(int $id): ?ContactDto;
}
```

```php
final class ContactController
{
    public function __construct(private readonly ContactsApi $contacts) {}
}
```

Register the interfaces in your extension's `Configuration/Services.php`:

```php
return static function (ContainerConfigurator $c, ContainerBuilder $builder): void {
    OutboundServices::discover($builder, __DIR__.'/../Classes/Api');
};
```

## What you get

- Endpoints as interface methods, DTOs mapped with [Valinor](https://github.com/CuyZ/Valinor), no request classes
- Retries that never repeat a POST unless declared safe, a capped `Retry-After`, a time budget per web request
- Response cache with tags, ETag revalidation, stale-on-error and page cache integration
- Shared rate limit, circuit breaker and OAuth token per connection
- HTTPS only, host allow lists (also for next-page links), private networks blocked, redirects off, response size limit, redacted logs
- Format profiles for plain JSON with problem details, JSON:API and Hydra; five pagination styles; deferred parallel calls
- Commands: `outbound:debug`, `outbound:lint`, `outbound:call --dry-run`, `outbound:health`, `outbound:cache:flush`, `outbound:make:dto`, `outbound:generate`
- Endpoint-based mocks for tests

## Try it

```bash
composer install
composer demo        # Petstore frontend on http://localhost:8080, live; append ?mock=1 for fixtures
composer test        # unit tests
composer test:live   # the same Petstore interfaces against petstore3.swagger.io
```

## Documentation

See [docs](docs/README.md).

## License

GPL-2.0-or-later
