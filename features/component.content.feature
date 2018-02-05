Feature: Content Component
  In order to manage content components
  As an API user
  I can perform all known requests with customisations and receive expected responses

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: I want a content component
    When I send a POST request to "/component/contents" with body:
    """
    {
      "content": "<p>My content</p>"
    }
    """
    Then the response status code should be 201
    And save the entity id as component
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/components/content.json"

  Scenario: I want a page for my component
    When I send a POST request to "/pages" with body:
    """
    {
      "title": "Page Title",
      "metaDescription": "Page Meta Description"
    }
    """
    Then the response status code should be 201
    And save the entity id as page

  Scenario: I want to add a component location:
    Given the json variable location_post is:
    """
    {}
    """
    And the node component of the json variable location_post is equal to the variable component
    And the node content of the json variable location_post is equal to the variable page
    When I send a "POST" request to "/component_locations" with the json variable location_post as the body
    Then the response status code should be 201
    And save the entity id as component_location

  @dropSchema
  Scenario: I want to delete a content component
    When I send a DELETE request to the entity component
    Then the response status code should be 204
