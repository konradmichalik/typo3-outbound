# Documentation

Four groups, in the order you meet them: declare an API, add the behaviour it needs, run it in an installation, understand what happens underneath.

| Group | What's inside |
|-------|---------------|
| [Defining endpoints](endpoints/README.md) | Connections, resources, endpoints, parameters, return types, DTOs, large APIs, naming |
| [Features](features/README.md) | Caching, retries and timeouts, rate limits, circuit breaker, pagination, formats, deferred calls, API conventions |
| [Operating](operating/README.md) | Installation, configuration, caches, commands, testing, the Petstore demo |
| [Background](background/README.md) | Architecture, security model, performance, comparison, deviations from the concept |

Pilot preparation: [migrating a service wrapper](operating/migration.md), [wrapper audit](background/wrapper-audit.md), [pilot evaluation](background/pilot-evaluation.md). `Build/verify.sh` runs every check that needs DDEV, a database or network access.
