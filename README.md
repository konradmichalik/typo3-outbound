<div align="center">

![Extension icon](Resources/Public/Icons/Extension.svg)

# TYPO3 extension `typo3_outbound`

![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%20to%208.5-blue.svg?logo=php)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

</div>

This extension lets you describe **external APIs as PHP interfaces with attributes**: the base URI, authentication, endpoints, retries, caching and rate limits are declared where the API is defined, and the extension generates the implementation. Your code receives typed DTOs instead of raw responses.

Without it, each external API tends to get a hand-written service wrapper with its own HTTP setup, retry loop and idea of what happens when the API is down. The wrappers then disagree on timeouts, caching and how secrets reach a request, and safety checks are the first thing left out.

> [!NOTE]
> The goal is the outgoing counterpart of [typo3-routing](https://github.com/konradmichalik/typo3-routing): where a `#[Route]` declares an endpoint your site offers, an `#[Outbound\Get]` declares one your site consumes, with safe defaults instead of a wrapper class per API.

## ✨ Features

- [**Interfaces as clients**](docs/endpoints/README.md): endpoints are interface methods, responses are mapped to DTOs with [Valinor](https://github.com/CuyZ/Valinor), no request classes
- [**Resilience**](docs/features/README.md#timeouts-and-retries): timeouts, a time budget per web request, retries that never repeat a POST unless declared safe, a capped `Retry-After`
- [**Caching**](docs/features/README.md#caching): response cache with tags, ETag revalidation, stale-on-error and page cache integration
- [**Shared limits**](docs/features/README.md#rate-limits-and-circuit-breaker): rate limit, circuit breaker and OAuth token per connection
- [**Safe defaults**](docs/background/README.md#security-model): HTTPS only, host allow lists, private networks blocked, redirects off, response size limit, redacted logs
- [**Formats and pagination**](docs/features/README.md#formats): plain JSON with problem details, JSON:API and Hydra, five pagination styles, deferred parallel calls
- [**Tooling**](docs/operating/README.md#commands): `outbound:debug`, `outbound:lint`, `outbound:call --dry-run`, `outbound:health`, `outbound:cache:flush`, `outbound:make:dto`, `outbound:generate`
- [**Testing**](docs/operating/README.md#testing): endpoint-based mocks for unit and functional tests, never by URL

## 🔥 Installation

### Requirements

* TYPO3 >= 13.4
* PHP 8.2+

### Composer

``` bash
composer require konradmichalik/typo3-outbound
```

### TER

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/typo3_outbound).

## 🚀 Quick start

Declare the API as an interface carrying `#[Connection]` and annotate a method with an HTTP verb:

```php
use KonradMichalik\Typo3Outbound\Attribute as Outbound;

#[Outbound\Connection(baseUri: 'https://api.weather.example.com')]
interface WeatherApi
{
    /** @return array{city: string, temperature: float} */
    #[Outbound\Get('/forecast/{city}')]
    public function forecast(string $city): array;
}
```

Inject the interface wherever you need it and call the method:

```php
final readonly class ForecastService
{
    public function __construct(private WeatherApi $weather) {}

    public function temperatureIn(string $city): float
    {
        return $this->weather->forecast($city)['temperature'];
    }
}
```

That's it: `temperatureIn('Dresden')` sends `GET https://api.weather.example.com/forecast/Dresden`, checks the JSON response against the declared shape and reads its `temperature`. For larger responses, return a DTO class instead of an array shape. Every interface with `#[Connection]` or `#[Resource]` that your `Configuration/Services.yaml` resource covers becomes a service. For interfaces outside that resource or in one of its `exclude` paths, call `OutboundServices::discover($builder, $directory)` in `Configuration/Services.php`.

Everything else is opt-in on top of that. A larger API splits into resources, takes its base URI and secret from the site configuration, maps responses to DTOs, retries, caches and maps a 404 to `null`, all declared with attributes:

```php
use KonradMichalik\Typo3Outbound\Value\{Secret, SiteSetting};

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

See [Defining endpoints](docs/endpoints/README.md) for connections, resources, parameters and return types, and [Features](docs/features/README.md) for every opt-in attribute.

## 📚 Documentation

Full documentation lives in [`docs/`](docs/README.md), grouped into four parts. The pilot material has a row of its own:

| Part | What's inside |
|------|---------------|
| [Defining endpoints](docs/endpoints/README.md) | Connections, resources, endpoints, parameters, return types, DTOs and naming: the only part you need to call an API |
| [Features](docs/features/README.md) | Caching, retries and timeouts, rate limits, circuit breaker, pagination, formats, deferred calls and API conventions |
| [Operating it](docs/operating/README.md) | Installation, configuration, caches, the console commands, testing with mocks and the Petstore demo |
| [Background](docs/background/README.md) | Architecture, security model, performance, comparison with the alternatives and deviations from the concept |
| [Migrating a wrapper](docs/operating/migration.md) | How to replace an existing service wrapper step by step, with the [wrapper audit](docs/background/wrapper-audit.md) and the [pilot evaluation](docs/background/pilot-evaluation.md) |

> [!TIP]
> Offering endpoints instead of consuming them? <img src="https://github.com/konradmichalik/typo3-routing/raw/main/Resources/Public/Icons/Extension.png?raw=true" width="16" height="16" alt=""> [`typo3-routing`](https://github.com/konradmichalik/typo3-routing) declares frontend endpoints with a `#[Route]` attribute on a controller method.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
