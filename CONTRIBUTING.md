# Contributing

Thank you for considering contributing to this project! Every contribution is welcome and helps improve the quality of the project. To ensure a smooth process and maintain high code quality, please follow the steps below.

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/) with the add-on [ddev-typo3-multi-version-extension][2]

## Preparation

```bash
# Clone repository
git clone https://github.com/konradmichalik/typo3-outbound.git
cd typo3-outbound

# Start the project with DDEV
ddev start

# Install dependencies
ddev composer install
```

## Run linters

```bash
# All linters
ddev composer cgl lint

# Specific linters
ddev composer cgl lint:composer
ddev composer cgl lint:editorconfig
ddev composer cgl lint:php

# Fix all CGL issues
ddev composer cgl fix

# Fix specific CGL issues
ddev composer cgl fix:composer
ddev composer cgl fix:editorconfig
ddev composer cgl fix:php
```

## Run static code analysis

```bash
# All static code analyzers
ddev composer cgl sca

# Specific static code analyzers
ddev composer cgl sca:php

# Rector (dry run) and dependency analysis
ddev composer cgl migration:rector -- --dry-run
ddev composer cgl analyze:dependencies
```

## Run tests

```bash
# Unit tests
ddev composer test

# Unit tests with code coverage
ddev composer test:coverage

# Functional tests (need a database, SQLite is enough)
ddev exec env typo3DatabaseDriver=pdo_sqlite composer test:functional
```

## TYPO3 Setup

For testing the extension, you need to set up the TYPO3 instances.

```bash
# Install all TYPO3 versions, which are supported by the extension
ddev install all

# Or install specific TYPO3 versions
ddev install 13
ddev install 14

# Open the overview page
ddev launch

# Run TYPO3 specific commands
ddev 14 typo3 outbound:debug
ddev 13 composer install
ddev all typo3 cache:flush
```

Both instances install the fixture extension `outbound_petstore` as sitepackage, so the Petstore plugin renders on https://13.typo3-outbound.ddev.site and https://14.typo3-outbound.ddev.site. Set `OUTBOUND_PETSTORE_MOCK=1` in `.ddev/docker-compose.zz-project.yaml` to answer from the fixture responses instead of petstore3.swagger.io. The public Petstore is writable by anyone and often returns broken data (a tag as a string, a pet without a name, HTTP 500), which fails with `ApiMappingFailed` or makes the live tests red. That is not a bug in the extension: use the mock for a stable result.

The DDEV setup comes from the add-on [ddev-typo3-multi-version-extension][2]; update it with `ddev add-on get konradmichalik/ddev-typo3-multi-version-extension`. `config.yaml`, `docker-compose.zz-project.yaml`, `.setup/project.sh`, `.setup/hooks/` and `commands/web/benchmark` belong to this repository and survive updates. The `post-typo3-setup` hook removes the page TypoScript that `typo3 setup` creates, because it would hide the TypoScript of the Petstore site set.

The standalone Petstore demo and the live tests belong to the core package [`konradmichalik/php-outbound`](https://github.com/konradmichalik/php-outbound).

## Performance benchmark

`ddev benchmark` renders the Petstore plugin in one TYPO3 instance with fixture responses, so no network latency is involved, and aggregates the profiles of [`konradmichalik/typo3-request-profiler`][3]. On its first run in an instance it adds the profiler, which moves Guzzle back to 7.x there until [#73](https://github.com/konradmichalik/typo3-request-profiler/issues/73) is resolved. Three scenarios run one after another: a page cache hit, an uncached page with a response cache hit, and an uncached page with the response cache flushed before every request.

```bash
# Lowest supported TYPO3 version, 50 requests per scenario
ddev benchmark

# TYPO3 14, 100 requests per scenario
ddev benchmark 14 100
```

The report shows the median and p95 of `timing.total_ms` and the median query count per scenario. Current figures are in [Background](docs/background/README.md#performance).

## Full verification

`Build/verify.sh` runs every check that needs DDEV, a database or network access in one go: linters, static analysis, unit and functional tests, the console commands in both TYPO3 instances and the Petstore plugin. It never stops at the first failure and prints a summary at the end.

```bash
# All steps
Build/verify.sh

# Without the steps calling petstore3.swagger.io: live tests, outbound:health and the Petstore plugin
Build/verify.sh --no-live
```

## Submit a pull request

After completing your work, **open a pull request** and provide a description of your changes. Ideally, your PR should reference an issue that explains the problem you are addressing.

All mentioned code quality tools run automatically on pull requests and on pushes to `main`. For more details, see the relevant [workflows][1].

[1]: .github/workflows
[2]: https://github.com/konradmichalik/ddev-typo3-multi-version-extension
[3]: https://github.com/konradmichalik/typo3-request-profiler
