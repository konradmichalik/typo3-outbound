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

# Petstore interfaces against petstore3.swagger.io (needs network)
ddev composer test:live
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

Both instances install the fixture extension `outbound_petstore` as sitepackage, so the Petstore plugin renders on the start page.

## Full verification

`Build/verify.sh` runs every check that needs DDEV, a database or network access in one go: linters, static analysis, unit and functional tests, the console commands in both TYPO3 instances and the Petstore plugin. It never stops at the first failure and prints a summary at the end.

```bash
# All steps
Build/verify.sh

# Without the calls to petstore3.swagger.io
Build/verify.sh --no-live
```

## Submit a pull request

After completing your work, **open a pull request** and provide a description of your changes. Ideally, your PR should reference an issue that explains the problem you are addressing.

All mentioned code quality tools run automatically on every push. For more details, see the relevant [workflows][1].

[1]: .github/workflows
[2]: https://github.com/konradmichalik/ddev-typo3-multi-version-extension
