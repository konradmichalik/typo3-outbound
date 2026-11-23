# Documentation

Declaring APIs and their behaviour is not specific to TYPO3 and is documented with the core package [`konradmichalik/php-outbound`](https://github.com/konradmichalik/php-outbound). This part covers running it in TYPO3.

| Group | What's inside |
|-------|---------------|
| [Defining endpoints](https://github.com/konradmichalik/php-outbound/blob/main/docs/endpoints/README.md) (core) | Connections, resources, endpoints, parameters, return types, DTOs, large APIs, naming |
| [Features](https://github.com/konradmichalik/php-outbound/blob/main/docs/features/README.md) (core) | Caching, retries and timeouts, rate limits, circuit breaker, pagination, formats, deferred calls, API conventions |
| [Operating](operating/README.md) | Installation, configuration, caches, commands, testing |
| [Background](background/README.md) | Performance in TYPO3; [architecture and security model](https://github.com/konradmichalik/php-outbound/blob/main/docs/background/README.md) in the core |

[Migrating a service wrapper](operating/migration.md) replaces an existing integration step by step, and lists what changed from 0.1 to 0.2. `Build/verify.sh` runs every check that needs DDEV, a database or network access.
