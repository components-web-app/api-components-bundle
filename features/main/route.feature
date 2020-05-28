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
      | /contact | contact-page | resource[page] | resource[route] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "route.schema.json"

  @loginUser
  Scenario: I can delete a route
    Given there is a DummyComponent
    When I send a "DELETE" request to the resource "dummy_component"
    Then the response status code should be 204

  Scenario: A route will output the nested redirect routes and data for the redirected page
    Given there is a Route "/contact" with redirects to "/contact-new"
    When I send a "GET" request to "/_/routes//contact"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "route.schema.json"
    And the JSON node redirectPath should be equal to the string "/contact-new"
    And the JSON node page should be equal to the string "resource[route_page]"
