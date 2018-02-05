Feature: Layout
  In order to use layouts
  As an API user
  I can perform all known requests with customisations and receive expected responses

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: I need a layout for the website
    When I send a "POST" request to "/layouts" with body:
    """
    {
      "default": true
    }
    """
    Then the response status code should be 201
    And save the entity id as layout
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"
    And the JSON node "default" should be true

  Scenario: Get a default layout
    When I send a "GET" request to "/layouts/default"
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"

  Scenario: Update layout
    When I send a "PUT" request to the entity layout with body:
    """
    {
      "default": false
    }
    """
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"
    And the JSON node "default" should be false

  Scenario: Get a default layout when one does not exist
    When I send a "GET" request to "/layouts/default"
    Then the response status code should be 404

  @dropSchema
  Scenario: I need to delete a layout
    When I send a "DELETE" request to the entity layout
    Then the response status code should be 204
