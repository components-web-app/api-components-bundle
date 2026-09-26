# API Components Bundle (Work in Progress)
[![SymfonyInsight](https://insight.symfony.com/projects/d5fac2e0-7a02-4f41-8427-b4ed49092bd9/big.svg)](https://insight.symfony.com/projects/d5fac2e0-7a02-4f41-8427-b4ed49092bd9)

[![CI](https://github.com/components-web-app/api-components-bundle/workflows/CI/badge.svg?branch=main)](https://github.com/components-web-app/api-components-bundle/actions?query=workflow%3ACI)
[![Maintainability](https://api.codeclimate.com/v1/badges/6d388db1c65f6a76a41c/maintainability)](https://codeclimate.com/github/components-web-app/api-components-bundle/maintainability)
[![codecov](https://codecov.io/gh/components-web-app/api-components-bundle/branch/main/graph/badge.svg)](https://codecov.io/gh/components-web-app/api-components-bundle)

###### PHPUnit Testing Only

[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fcomponents-web-app%2Fapi-components-bundle%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/components-web-app/api-components-bundle/main)

#### Creates a flexible API for a website's structure, reusable components and common functionality.

### **[Read the documentation](https://docs.api.cwa.rocks/)**

## Requirements

- PHP 8.5
- Symfony `^7.4 || ^8.1`
- API Platform `^4.4 || ^5.0`
- Doctrine ORM 3

The bundle code supports Symfony 8. The Behat tooling (`behat/behat` 3.x, `friends-of-behat/mink-extension` 2.x) still caps some Symfony components at 7.4 in the test environment only.

## Testing

Run everything from the repository root after `composer install`.

### Setting up the test database

The Behat suite creates its own SQLite database on each run. To create one by hand, for example to inspect the schema:

```bash
php tests/Functional/app/bin/console -e test doctrine:database:create
php tests/Functional/app/bin/console -e test doctrine:schema:create
php tests/Functional/app/bin/console -e test doctrine:schema:validate
```

### PHPUnit (unit tests)

```bash
php -d memory_limit=512M vendor/bin/phpunit
```

Unit tests cover pure logic and service wiring. They never boot a kernel: wiring is asserted by loading the bundle's config into a bare `ContainerBuilder`. Tests that need a database use an in-memory SQLite `EntityManager`.

### Behat (API behaviour)

```bash
php -d memory_limit=-1 bin/behat-parallel             # the whole suite in concurrent shards (what CI runs)
php -d memory_limit=-1 vendor/bin/behat               # the whole suite, sequentially
php -d memory_limit=-1 vendor/bin/behat features/main/route.feature   # one feature
```

`bin/behat-parallel` splits the feature files across shards (by default one per CPU, at most 4) and runs them at the same time. Each shard gets its own cache, SQLite database and upload directory (`BEHAT_SHARD=<n>`, under `tests/Functional/app/var/shard-<n>/`), so shards can't interfere with each other.

- `--shards=N` sets the number of shards.
- `--list` shows which feature files go to each shard.
- Everything after `--` is passed to every Behat process, e.g. `bin/behat-parallel -- --tags='~@wip'`.

The full suite takes about 40 seconds on a 10-core machine, compared with about 100 seconds run sequentially.

If a shard fails but the same feature passes on its own, two scenarios probably depend on the order they run in. Reproduce it sequentially with that shard's files: `vendor/bin/behat <files from --list>`.

Prefer Behat scenarios for API behaviour and PHPUnit for pure logic. Every new behaviour or bug fix starts with a test that fails first.

### Coverage

```bash
php -d memory_limit=-1 -d pcov.enabled=1 bin/behat-parallel -- --profile=default-coverage
```

The shards' coverage is merged into one Clover report at `build/logs/behat/clover.xml`. CI fails the Behat coverage job if fewer than 1000 statements are covered, and uploads the report to Codecov alongside the PHPUnit coverage.

### Mutation testing (Infection)

Infection runs in CI on the PHPUnit coverage job, with a covered-code MSI gate of 80%. It is not a Composer dependency. Download the signed phar at the version pinned in `.github/workflows/ci.yml` (`INFECTION_VERSION`) and run it against PHPUnit's coverage:

```bash
php -d pcov.enabled=1 vendor/bin/phpunit --configuration=phpunit.coverage.xml.dist \
  --coverage-xml=build/logs/phpunit/coverage-xml --log-junit=build/logs/phpunit/junit.xml
php infection.phar --coverage=build/logs/phpunit --min-covered-msi=80
```

In CI, a pull request runs Infection only on the lines it adds or changes in `src/` (`--git-diff-lines`), with the same 80% gate applied to those lines. A PR with no PHP changes passes. Every push to `main` runs the full mutation set, and only that run updates the Stryker badge.

Infection only scores PHPUnit coverage, so a class covered only by Behat gets no mutation testing. Give new classes unit tests as well.

### Static analysis and coding standards

```bash
vendor/bin/phpstan analyse --memory-limit=1G   # level 5, against phpstan-baseline.neon
vendor/bin/php-cs-fixer fix                     # run before every commit; CI fails on violations
```

A PHPStan fix that removes a baselined finding must also remove its entry: regenerate the baseline with `vendor/bin/phpstan analyse --memory-limit=1G --generate-baseline phpstan-baseline.neon`.

### What CI runs

Every pull request and every push to `main` runs these checks:
- Behat on six Symfony and API Platform combinations, including lowest dependencies. Each job runs the suite in 4 shards.
- PHPUnit on four Symfony versions. The Symfony 7.4 job also collects coverage and runs Infection.
- PHPStan, php-cs-fixer and GitGuardian.
- Codecov patch and project coverage.

## Sponsors

[Blackfire](https://blackfire.io/)

## Contributors ✨

Thanks goes to these wonderful people ([emoji key](https://allcontributors.org/docs/en/emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->
<table>
  <tr>
    <td align="center"><a href="https://les-tilleuls.coop"><img src="https://avatars1.githubusercontent.com/u/407859?v=4" width="60px;" alt=""/><br /><sub><b>Vincent</b></sub></a><br /><a href="https://github.com/components-web-app/api-components-bundle/commits?author=vincentchalamon" title="Code">💻</a> <a href="#ideas-vincentchalamon" title="Ideas, Planning, & Feedback">🤔</a> <a href="https://github.com/components-web-app/api-components-bundle/pulls?q=is%3Apr+reviewed-by%3Avincentchalamon" title="Reviewed Pull Requests">👀</a></td>
    <td align="center"><a href="https://github.com/PierreRebeilleau"><img src="https://avatars1.githubusercontent.com/u/49146882?v=4" width="60px;" alt=""/><br /><sub><b>Pierre Rebeilleau</b></sub></a><br /><a href="https://github.com/components-web-app/api-components-bundle/commits?author=PierreRebeilleau" title="Tests">⚠️</a></td>
    <td align="center"><a href="https://github.com/chalasr"><img src="https://avatars0.githubusercontent.com/u/7502063?v=4" width="60px;" alt=""/><br /><sub><b>Robin Chalas</b></sub></a><br /><a href="https://github.com/components-web-app/api-components-bundle/commits?author=chalasr" title="Code">💻</a></td>
    <td align="center"><a href="https://soyuka.me"><img src="https://avatars3.githubusercontent.com/u/1321971?v=4" width="60px;" alt=""/><br /><sub><b>Antoine Bluchet</b></sub></a><br /><a href="https://github.com/components-web-app/api-components-bundle/issues?q=author%3Asoyuka" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://twitter.com/maxhelias"><img src="https://avatars2.githubusercontent.com/u/12966574?v=4" width="60px;" alt=""/><br /><sub><b>Maxime Helias</b></sub></a><br /><a href="https://github.com/components-web-app/api-components-bundle/commits?author=maxhelias" title="Documentation">📖</a></td>
  </tr>
</table>

<!-- markdownlint-enable -->
<!-- prettier-ignore-end -->
<!-- ALL-CONTRIBUTORS-LIST:END -->

This project follows the [all-contributors](https://github.com/all-contributors/all-contributors) specification. Contributions of any kind are welcome!
