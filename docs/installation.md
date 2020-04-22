---
layout: default
title: Installation
nav_order: 0
---
# Install API Component Bundle
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

> __Be sure to run the [recipe for this bundle](https://github.com/api-platform/api-platform) or take a look at all the files and configurations in the repository that would normally have been executed if the recipe had been executed.__
