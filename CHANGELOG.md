# Changelog

## alpha.2
- Added getters and setters for `ComponentPosition::$pageDataProperty`
- `RouteGenerator::createFromPage` refactored to `RouteGenerator::create`
- `RouteGenerator::create` now accepts any object implementing new `RoutableIterface`
- Added `RouteGeneratorInterface`
- Added `RoutableInterface`
- Add `RouteGeneratorInterface` alias to service `silverback.helper.route_generator`
- Bug fix - RouteGenerator allow for old entity not to have a route index
- Bug fix - route generator will also persist timestamp fields
- Bug fix - mapping of parentRoute. Change to Many-To-One from One-To-One.
- ComponentPositionNormalizer - do not normalize if no request
- Simple use of parentRoute of pageData (data structure needs a re-think though - parent routes, parent pages and how they should work)
- Bug fix: Only apply doctrine extension for routes to the route resources
- Bug fix: Return expired jwt cookie on logout
