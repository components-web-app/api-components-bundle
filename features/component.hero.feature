Feature: Hero Component
  In order to manage hero components
  As an API user
  I can perform all known requests with customisations and receive expected responses

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: I want a hero component
    When I send a POST request to "/component/heroes" with body:
    """
    {
      "title": "Hero Title",
      "subtitle": "Hero Subtitle"
    }
    """
    Then the response status code should be 201
    And save the entity id as hero
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/components/hero.json"

  @dropSchema
  Scenario: I want to delete a hero component
    When I send a DELETE request to the entity hero
    Then the response status code should be 204
