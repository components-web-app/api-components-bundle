Feature: The cache tags a Route write purges
  In order for a front end to tag a cached sitemap with the Route collection and have every route write drop it
  As a front end which emits that tag itself and so must match it byte for byte
  I need a Route write to purge exact tags that carry the prefix the API routes are imported under, from HTTP and from the CLI alike

  Background:
    Given the API routes are imported under the prefix "/_api"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: Creating a route purges the prefixed route collection and the route's own IRI
    Given there is a Page
    When I send a "POST" request to "/_api/_/routes" with data:
      | path      | name     | page           |
      | /my-route | my-route | resource[page] |
    Then the response status code should be 201
    And the cache tag "/_api/_/routes" should be purged
    And the cache tag "/_api/_/routes//my-route" should be purged
    And the cache tag "/_/routes" should not be purged

  @loginAdmin
  Scenario: Changing a route's path purges the prefixed route collection and the IRIs of both paths
    Given there is a Route "/my-route" with a page
    When I send a "PATCH" request to "/_api/_/routes//my-route" with data:
      | path          |
      | /renamed-route |
    Then the response status code should be 200
    And the cache tag "/_api/_/routes" should be purged
    And the cache tag "/_api/_/routes//renamed-route" should be purged
    And the cache tag "/_api/_/routes//my-route" should be purged

  @loginAdmin
  Scenario: Deleting a route purges the prefixed route collection and the route's own IRI
    Given there is a Route "/my-route" with a page
    When I send a "DELETE" request to "/_api/_/routes//my-route"
    Then the response status code should be 204
    And the cache tag "/_api/_/routes" should be purged
    And the cache tag "/_api/_/routes//my-route" should be purged

  Scenario: A route written outside an HTTP request purges the same prefixed tags
    When a Route with the path "/cli-route" is written outside an HTTP request
    Then the cache tag "/_api/_/routes" should be purged
    And the cache tag "/_api/_/routes//cli-route" should be purged
    And the cache tag "/_/routes" should not be purged
