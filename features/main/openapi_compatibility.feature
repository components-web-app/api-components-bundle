Feature: API Platform Swagger Compatibility
  In order to use the swagger interactive documentation
  As an user
  I can visit the API documentation page

  Scenario: I can view the Swagger API Docs
    When I send a "GET" request to "/"
    Then the response status code should be 200

  Scenario: The API version string is extended with the bundle package version in parentheses
    Given I add "Accept" header equal to "application/json"
    When I send a "GET" request to "/docs.json"
    Then the response status code should be 200
    And the JSON node "info.version" should contain "("

  Scenario: Concrete component endpoints are included in the OpenAPI paths
    Given I add "Accept" header equal to "application/json"
    When I send a "GET" request to "/docs.json"
    Then the response status code should be 200
    And the JSON node "paths./component/dummy_components" should exist
