Feature: Route resources
  In order to create deterministic endpoints for the front-end
  As an API user
  I can add create a route resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a route
    Given there is a Page
    And there is a Route "/other" with a page
    When I send a "POST" request to "/_/routes" with data:
      | path     | name         | page            | redirect         |
      | /contact | contact-page | resource[page]  | resource[route] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "route.schema.json"

  @loginUser
  Scenario: I can delete a route
    Given there is a Route "/deletable" with a page
    When I send a "DELETE" request to the resource "route"
    Then the response status code should be 204

  Scenario: A route will output the nested redirect routes and data for the redirected page
    Given there is a Route "/contact" with redirects to "/contact-new"
    When I send a "GET" request to "/_/routes//contact"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "route.schema.json"
    And the JSON node redirectPath should be equal to the string "/contact-new"
    And the JSON node page should be equal to the string "resource[route_page]"

  # Route generate
  @loginUser
  Scenario: I can automatically generate a route from a PageData resource
    Given there is a PageData resource with the route path null
    When I send a "POST" request to "/_/routes/generate" with data:
     | pageData            |
     | resource[page_data] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "route.schema.json"
    And the JSON node "path" should be equal to the string "/unnamed-page"
    And the JSON node "name" should be equal to the string "unnamed-page"

  @loginUser
  Scenario: I generate a route for page data with a pre-existing route. The original route will change to a redirect.
    Given there is a PageData resource with the route path "/original"
    When I send a "POST" request to "/_/routes/generate" with data:
      | pageData            |
      | resource[page_data] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "route.schema.json"
    And the Route "/original" should redirect to "/unnamed-page"

  @loginUser
  Scenario: I generate a route for a path that already exists and the new route is generated with a postfix
    Given there is a PageData resource with the route path "/unnamed-page"
    When I send a "POST" request to "/_/routes/generate" with data:
      | pageData            |
      | resource[page_data] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "route.schema.json"
    And the JSON node "path" should be equal to the string "/unnamed-page-1"
    And the JSON node "name" should be equal to the string "unnamed-page-1"

  @loginUser
  Scenario: I can get all the redirects for a route in a single call
    Given there is a Route "/redirect1" with redirects to "/unnamed-page"
    When I send a "GET" request to the resource "final_route" and the postfix "/redirects"
    Then the response status code should be 200
    And the JSON node "@id" should exist
    And the JSON node "path" should be equal to the string "/unnamed-page"
    And the JSON node "redirectedFrom[0]" should exist
    And the JSON node "redirectedFrom[0].redirectedFrom" should exist
    And the JSON node "redirectedFrom[0].redirectedFrom[0].path" should be equal to the string "/redirect1"
