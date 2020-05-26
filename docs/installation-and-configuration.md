---
layout: default
title: Installation & Configuration
nav_order: 0
---
# Installation & Configuration
{: .no_toc }

## Table of contents
{: .no_toc .text-delta }

* TOC
{:toc}

## Recommended Installation

Please see the [Components Web App](https://github.com/components-web-app/components-web-app) template repository. This includes a complete setup with the front-end application using our own Nuxt module as well. It also includes testing frameworks setup by default so you can start writing tests for your application immediately, a `docker-compose.yaml` configuration for local development, a helm chart for Kubernetes and a complete Gitlab devops configuration for a production environment.

## Bundle configuration

You can configure the name of the website the API is for which is used in a number of places, such as the default email signatures, and the API Swagger documentation. You can also adjust the default prefix applied to tables in your database. This is to prevent any naming conflicts.

### Website name (required)

This is used for a number of default features, including email signatures and adding a default title to your swagger documentation (if you remove the title configuration from the API Platform recipe)

```yaml
silverback_api_component:
    website_name: ~ # Required
```

### Table prefix

To prevent table name conflicts, we automatically prefix `_acb_` to the database tables that API Component Bundle manages. You can customise this using this configuration.

```yaml
silverback_api_component:
    table_prefix: _acb_
```

### Metadata key

Resources handled by API Components Bundle will include metadata. We inject this into a variable in your output which is `_metadata` by default. You can customise this in your configuration.

```yaml
silverback_api_component:
    metadata_key: _metadata
```


## Manual Installation

We encourage using as many of the packages as possible that are well maintained by large and active communities. Therefore, let's start with the most up to date API Platform files and then install this bundle on top.

In the future, we will be creating a standard package you will be able to use for installing ACB instead of needing to follow these instructions. For now, we want to just focus on getting this bundle working well without the additional repository to maintain.

### Setup
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

### Install Api Components Bundle
- run `composer require silverbackis/api-components-bundle:dev-master`

> __Be sure to run the [recipe for this bundle](https://github.com/api-platform/api-platform) or take a look at all the files and configurations in the repository that would normally have been executed if the recipe had been executed.__
