# API Component Bundle v2
[![Build Status](https://travis-ci.org/silverbackis/ApiComponentBundle.svg?branch=v2)](https://travis-ci.org/silverbackis/ApiComponentBundle)
[![codecov](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/v2/graph/badge.svg)](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/v2)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/quality-score.png?b=v2)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=v2)

The diagram below shows what this bundle aims to implement by using Symfony and [API Platform](https://github.com/api-platform/api-platform)
![Api Component Bundle - Basic Flow](./docs/images/API%20Component%20Bundle%20v2%20Flow.jpg)

This structure will allow a developer to create an API to provide UI structure and components resources from a database allowing front-end application to display and manipulate the resources.

It also includes the ability to have components where files can be uploaded, handling of forms (serialization, validation and successful submissions) and a built-in component to display a collection of resources with filtering and pagination (this is more of a proxy component to allow collections of component of dynamic page resources to be included within a page).

You'll notice there is a 'Abstract Page Data' base class for a resource. This will be used for pages where the template should be the same or very similar (for example Blog Articles). These pages will have routes assigned to them automatically based on the page titles.

When 'Routes' change we will also handle creating redirects from the old route to the new one where possible.

There is a lot to create and discuss here and most features were implemented in some way for version 1 of this bundle. Some methods by which the functionality is implemented will change and there is a lot of ways in which we are able to simplify this version of the bundle in comparison to the first version.

We hope by creating this we can provide a tool for developers and designers to easily create websites using re-usable modules which can be implemented with ease, extremely useful out-of-the-box functionality and for it to be easy to build upon.

## The front-end web application
A starting point for a front-end web application using this API will be built as well and once complete will include security features and a couple of simple examples of components which can be modified by a website administrator. Our example will be a progressive web app using VueJS and Nuxt. We have created this for our 1st version of this bundle as an experiment but it will need to be re-made. There will be a link here once there is something to see.

## Installation
We encourage using as many of the packages as possible that are well maintained by large and active communities. Therefore let's start with the most up to date API Platform files and then install this bundle on top.
- Download [API Platform files from GitHub](https://github.com/api-platform/api-platform) as described in their ['Getting Started'](https://api-platform.com/docs/core/getting-started/) instructions
- Delete the folders `/client` and `/admin` - we do not need these
- Remove the client and admin configurations from the `/docker-compose.yaml` file
- Update the `api/Dockerfile`
  - Change PHP version to at least 7.4
  - Remove `--with-libzip` if present
  - Add `exif` to the `docker-php-ext-install` arguments
- Start up the containers
- run `docker-compose exec php sh` to bash into the php container
- run `composer require silverbackis/api-component-bundle:2.x-dev`
