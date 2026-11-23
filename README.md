<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_outbound`

[![Latest Stable Version](https://typo3-badges.dev/badge/typo3_outbound/version/shields.svg)](https://extensions.typo3.org/extension/typo3_outbound)
![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014-orange.svg)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-outbound/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-outbound)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-outbound/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-outbound/actions/workflows/cgl.yml)
[![Coverage](https://coveralls.io/repos/github/konradmichalik/typo3-outbound/badge.svg?branch=main)](https://coveralls.io/github/konradmichalik/typo3-outbound)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-outbound/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-outbound/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/konradmichalik/typo3-outbound/license)](LICENSE.md)

</div>

This extension lets you describe **external APIs as PHP interfaces with attributes** and generates the implementation. Your code calls methods and receives typed objects, without HTTP code of its own.

> [!NOTE]
> The goal is to replace the hand-written service wrapper per API: you declare what an API offers, and timeouts, retries, caching and security come as safe defaults.

> [!WARNING]
> This extension is at an early stage. Attributes and APIs may still change before 1.0, so try it in a pilot before relying on it in production.

## ✨ Features

- [**Less code per API**](docs/endpoints/README.md): one interface method per endpoint and typed DTOs instead of request classes and HTTP plumbing
- [**Fast pages**](docs/features/README.md#caching): responses are cached and tied to the page cache, and a write can clear the pages showing the changed data
- [**Pages keep rendering when an API fails**](docs/features/README.md#timeouts-and-retries): short timeouts, safe retries, a circuit breaker and, if you allow it, stale data to bridge an outage
- [**Secure by default**](docs/background/README.md#security-model): HTTPS only, allowed hosts, private networks blocked, secrets from the environment and redacted logs
- [**Made for TYPO3**](docs/operating/README.md): per-site credentials from site settings, the caching framework and console commands to inspect and call every endpoint
- [**Quick start from OpenAPI**](docs/operating/README.md#commands): interfaces and DTOs generated from an OpenAPI document or a sample response
- [**Tests without network**](docs/operating/README.md#testing): mocks by endpoint instead of URL, in unit and functional tests

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

That's it: `temperatureIn('Dresden')` sends `GET https://api.weather.example.com/forecast/Dresden`, checks the JSON response against the declared shape and reads its `temperature`. For larger responses, return a DTO class instead of an array shape. Every interface with `#[Connection]` or `#[Resource]` that your `Configuration/Services.yaml` resource covers becomes a service.

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

Full documentation lives in [`docs/`](docs/README.md):

| Part | What's inside |
|------|---------------|
| [Defining endpoints](docs/endpoints/README.md) | Connections, resources, endpoints, parameters, return types, DTOs and naming: the only part you need to call an API |
| [Features](docs/features/README.md) | Caching, retries and timeouts, rate limits, circuit breaker, pagination, formats, deferred calls and API conventions |
| [Operating it](docs/operating/README.md) | Installation, configuration, caches, the console commands and testing with mocks |
| [Background](docs/background/README.md) | Architecture, security model, performance figures and the comparison with Saloon |
| [Migrating a wrapper](docs/operating/migration.md) | How to replace an existing service wrapper step by step |

> [!TIP]
> Offering endpoints instead of consuming them? <img src="https://github.com/konradmichalik/typo3-routing/raw/main/Resources/Public/Icons/Extension.png?raw=true" width="16" height="16" alt=""> [`typo3-routing`](https://github.com/konradmichalik/typo3-routing) declares frontend endpoints with a `#[Route]` attribute on a controller method.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
