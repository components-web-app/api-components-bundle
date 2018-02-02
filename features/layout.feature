Feature: Layouts
  In order to interact with layout entities
  As a website user
  I can use the API to perform CRUD operations on layouts

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Create layout
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

  Scenario: Get layout
    When I send a "GET" request to the entity layout
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

  @dropSchema
  Scenario: Delete layout
    When I send a "DELETE" request to the entity layout
    Then the response status code should be 204

