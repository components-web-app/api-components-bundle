# API Component Bundle v2
**Please be aware this document has been created in haste, but if you're interested in learning more about it or helping, please get in touch. It is currently just me working on this MIT licenced, free, open source framework in the hope to give back to the community and help develop my own business as well. Most urgently I would love tests to be written. If you can spare a moment to help I'd be very appreciative**

This project began in 2018 to provide a framework to define UI structure and components and useful endpoints and (de)serialization tools for a front-end web application. Progressive Web Apps have become more popular and for good reason.

This bundle aims to provide a reusable data structure and utilities for tasks that 99% of web applications require. From a developer's point of view it enables you to define UI components and structures easily and can then be paired with another project we are also working on - the Components Web App.

## Content Management
We know that you can create a front-end application and simply request specific resources from your API to populate the information. While that may work for many, this framework allows you to define the entire structure, which UI components to use where and encourages components and data to be re-used.

## Performance
Because the front-end application will need to request each page's structure it is important to use caches and optimise the response speeds as much as possible. We recommend always starting your project with a fresh API Component Bundle template as that has a number of very talented maintainers and they are always looking for performance improvements and server stacks with intelligent caching layers. Your front-end web application will have to be configured take advantage of these features as well, but our Components Web App will be built in Nuxt for those who want a starting point.

## File Uploads
Often components will require an administrator to be able to upload files and images. Currently we will only support uploading files directly to the API server, however in future we will be integrating with APIs and provide ways for you to build your own file upload handlers so you can take advantage of CDNs and the other benefits that come with external assets storage.

## Forms
The bundle will serialize forms, provide the ability to define handlers for form submissions and handle individual field validation requests.

## Installation
We encourage using as much of the packages that well maintained by large communities as possible. Therefore let's start with the most up to date API Platform files and then install this bundle on top.
- Download API Platform as per 'Getting Started' instructions
- Delete the folders `/client` and `/admin` - we do not need these
- Remove the client and admin configuration for the `/docker-compose.yaml` file
- Update the `api/Dockerfile`
  - Change PHP version to at least 7.4
  - Remove `--with-libzip` if present
  - Add `exif` to the `docker-php-ext-install` arguments
- Start up the containers
- run `docker-compose exec php sh` to bash into the php container
- run `composer require silverbackis/api-component-bundle:2.x-dev`

---
Dev note:
`php -d memory_limit=-1 /usr/local/bin/composer install --ignore-platform-reqs`
