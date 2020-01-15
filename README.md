# API Component Bundle v2
[![Build Status](https://travis-ci.org/silverbackis/ApiComponentBundle.svg?branch=v2)](https://travis-ci.org/silverbackis/ApiComponentBundle)
[![codecov](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/v2/graph/badge.svg)](https://codecov.io/gh/silverbackis/ApiComponentBundle/branch/v2)
[![Infection MSI]( https://badge.stryker-mutator.io/github.com/silverbackis/ApiComponentBundle/v2)](https://infection.github.io)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/badges/quality-score.png?b=v2)](https://scrutinizer-ci.com/g/silverbackis/ApiComponentBundle/?branch=v2)

The diagram below shows what this bundle aims to implement by using Symfony and [API Platform](https://github.com/api-platform/api-platform)
![Api Component Bundle - Basic Flow](./docs/images/API%20Component%20Bundle%20v2%20Flow.jpg)

This structure will allow a developer to create an API providing UI structure and component resources from a database, thereby allowing a front-end application to display and manipulate the resources.

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


## Example: Creating a new 1 page website
### Back-End Developer Work
__Each of these components should have the appropriate properties and may use Traits provided to help. E.g. a `Title` on a `Hero` component using a trait, or `copyrightText` on the `Footer` which would not use a trait__ 
1. Create `NavItem` component API resource
2. Create `Footer` component API resource
3. Create `Hero` component API resource
4. Create a Symfony form (with validation if you want) and `FormSuccessHandler`
 
### API
#### Layout
__Adding a new component to component groups with a `ComponentLocation` should be done in one action the the user of the front-end application. Otherwise this would be quite cumbersome to create the component resource in the API and then have to find the IDs of the appropriate component and component group to add a `ComponentLocation`__
1. Create a `Layout` API resource
2. Create 2 `ComponentGroup` resources within the `Layout`
3. Create a `Collection` component defined to display the `NavItem` resource. You could also define a specific front-end component to use such as `NavBar` that the web application can read to display the collection appropriately
4. Create a `ComponentLocation` resource placing the `Collection` into the 1st `ComponentGroup` of the `Layout`
5. Create `NavItem` resources for the navigation bar. You could configure your `NavItem` to have an optional relation to `ComponentGroup` and then insert nested `NavItem` resources that way if you desire a tree structure to your navigation
6. Create a `Footer` resource and a `ComponentLocation` to add it into the 2nd `ComponentGroup` for the `Layout`

#### Contact Page
1. Create a `PageTemplate` API resource
2. Create 1 `ComponentGroup` within this `PageTemplate`
3. Create a `Route` API resource which will direct a user to the `PageTemplate` you've just created. You now have a route directing a user to an empty page
4. Create a `Hero` API resource (with a title and any other properties you defined) - add this to the `ComponentGroup` with a `ComponentLocation` resource
5. Create a `Form` API resource defining the Symfony form type class and handler class you'd like to use and a `uiComponentName` of `ExampleForm`. Then add this to the `ComponentGroup` as above using a `ComponentLocation` resource.

### Front-end
__There will be examples of this in our sample Nuxt front-end application. The application will read in all of the API resources and save them all individually in a global store. It will also listen for any changes to any resource and update it in the store. Each mixin provided that you should use in components will be reading the component's data from the global store__

#### Layout
1. Create a layout and set the 1st `ComponentGroup` to render at the top and the 2nd at the bottom. The rest of the UI layout and styling is up to you. You'll want to include where you want your page to render as per your front-end framework's instructions too.
2. Create a `NavBar` component which will be used to render the `Collection` resource of `NavItem` resources.
3. Create `NavItem` component, making sure if you have a more nested `NavItem` resources within a `ComponentGroup` you also render these appropriately.

__If you have multiple layouts, in the API you will be able to specify a `UIComponentName` for the layout which will in turn use that as the layout name in the front end application which you can configure.__

#### Page
__There will be a default template used to simply render each `ComponentGroup` and `ComponentLocation` resources stacked on top of each other. In the API you will be able to define a different UI component to use if you want to arrange the `ComponentGroup` resources within `PageTemplate` (much like we did in the `Layout`) ___

1. Create a `Hero` component which will render the title. There will be mixins so you can easily get the component's data and more in relation to whether a user is logged in or not in future.
2. Create a `ExampleForm` component. There will be a mixin which will help you to render all the form fields with automatic validation and so much more.

**For all aspects of the front-end, this is an extremely brief overview. Examples and all functionality will be shown in far greater detail in the GitHub repository as we build it. Lots of the features were available in the 1st version of this system, but we realised now we have clarity over how it should all work and the features required, we need to start again. Watch this space!**