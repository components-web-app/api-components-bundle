# API Component Bundle v2 [WIP]

__Overall:__ 
[![CI](https://github.com/silverbackis/ApiComponentBundle/workflows/CI/badge.svg?branch=master)](https://github.com/silverbackis/ApiComponentBundle/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/master/graph/badge.svg)](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/master) <!-- [![Test Coverage](https://api.codeclimate.com/v1/badges/999310aca84ea8947ea9/test_coverage)](https://codeclimate.com/github/silverbackis/ApiComponentBundle/test_coverage) --> 
[![Maintainability](https://api.codeclimate.com/v1/badges/34e3843d9f9ec9777b0e/maintainability)](https://codeclimate.com/github/silverbackis/ApiComponentBundle/maintainability)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=master)

__PHPUnit:__ 
[![Code Coverage](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fsilverbackis%2FApiComponentBundle%2Fmaster)](https://infection.github.io)

---

## Installation
We encourage using as many of the packages as possible that are well maintained by large and active communities. Therefore, let's start with the most up to date API Platform files and then install this bundle on top.

In the future, we will be creating a standard package you will be able to use for installing ACB instead of needing to follow these instructions. For now, we want to just focus on getting this bundle working well without the additional repository to maintain.

- Download [API Platform files from GitHub](https://github.com/api-platform/api-platform) as described in their ['Getting Started'](https://api-platform.com/docs/core/getting-started/) instructions
- Delete the folders `/client` and `/admin` - we do not need these
- Remove the client and admin configurations from the `/docker-compose.yaml` file
- Update the `api/Dockerfile`
  - Change PHP version to at least 7.4
  - Remove `--with-libzip` if present
  - Add `COPY assets assets/` beneath `COPY src src/`
  - Add `exif` and `xsl` to the `docker-php-ext-install` arguments (exif is to determine whether files are images and xsl is for the Inky extension working with emails using Symfony Mailer)
  - Add `libxslt-dev` to `apk add --no-cache --virtual .build-deps` (required to install xsl)
  - For `LiipImagineBundle` Support
    - Add to `apk add --no-cache --virtual .build-deps` command the packages `libpng-dev`, `libjpeg-turbo-dev` and `freetype-dev`
    - Add the following to include gd `docker-php-ext-configure gd --with-freetype --with-jpeg`
    - Add or modify to include gd `docker-php-ext-install gd`
- Start up the containers
- run `docker-compose exec php sh` to bash into the php container
- run `composer require silverbackis/api-component-bundle:2.x-dev`

> Be sure to run the [recipe for this bundle](https://github.com/api-platform/api-platform) or take a look at all the files and configurations in the repository that would normally have been executed if the recipe was run. It includes route mapping, default package configuration and a default User entity definition.

## Getting Started
- [Users & Security](./docs/users_and_security/index.md)
  - [Emails](./docs/users_and_security/emails.md)
- [Resources](./docs/resources/index.md)
  - [Core resources](./docs/resources/core/index.md)
  - [Component resources](./docs/resources/components/index.md)
