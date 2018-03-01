# API Component Bundle

| Service | Branch: Master | Branch: Develop |
| :--- | :--- | :--- |
| Travis CI | [![Build Status](https://travis-ci.org/silverbackis/ApiComponentBundle.svg?branch=master)](https://travis-ci.org/silverbackis/ApiComponentBundle) | [![Build Status](https://travis-ci.org/silverbackis/ApiComponentBundle.svg?branch=develop)](https://travis-ci.org/silverbackis/ApiComponentBundle) |
| Codecov coverage | [![codecov](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/master/graph/badge.svg)](https://codecov.io/gh/silverbackis/ApiComponentBundle) | [![codecov](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/develop/graph/badge.svg)](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/develop) |
| Scrutinizer | [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=master) | [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=develop) |
| Scrutinizer coverage | [![Code Coverage](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=master) | [![Code Coverage](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=develop) |

## Introduction
This bundle is the main requirement of the API for the [BW Starter Website](https://github.com/silverbackis/BwStarterWebsite) which uses VueJS as the front-end and demonstrates how to use this bundle.

It provides common API functionality for component driven websites using Doctrine and [API Component Bundle](https://api-platform.com/)

The main features of this bundle include:
- Entities mapped and configured so they can be modified over an API that make up a website
- Support to retrieve and handle Symfony Forms including validation
- Extendable so you can create more components

## Entities
The simplest way to understand introduce you to the entities is with this simple hierarchy.
- Layout
- Content
- Components

Content can be a ComponentGroup or a Page. ComponentGroups can be children of any component, and pages can only be a child of the layout. All Content entities have components as children. This allows for infinite nesting of components.

More documentation will be added as the bundle is developed and the BW Starter Website will continue to be developed using this bundle and will provide examples on how this bundle can be used in it's simplest form.

## Factories
Every entity has a factory which can be used to create the entity. Options are passed to a factory as an array and an exception thrown if an unsupported key is defined. Entities are also validated when created with a factory, with an exception thrown if validation fails.

## Contributing
Contributions are welcome - even if it is just writing a test for a feature that already exists. [Report new issues](https://github.com/silverbackis/ApiComponentBundle/issues) or [Create a pull request](https://github.com/silverbackis/ApiComponentBundle/pulls)

## Testing
As some tests are in Symfony and others use behat, please be sure not to run symfony's `/vendor/bin/simple-phpunit` and instead run `/bin/phpunit` if you are nice enough to contribute any time and energy to improving this bundle. This script simply sets the PHPUnit version first so that no matter the environment a default PHPUnit version is installed and Behat/The test Kernel can use this as well.
