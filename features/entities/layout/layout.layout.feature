Feature: Layout
  To use default layouts
  As an API user
  I can add a default layout, access it via the default route and a page should always return the default value if not set

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @updateDatabaseSchema
  Scenario: I want to add a default layout that pages will default to
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

  Scenario: A page without a layout should return the default layout
    Given the json variable page_post is:
    """
    {
      "title": "Page Title",
      "metaDescription": "Page Meta Description"
    }
    """
    When I send a "POST" request to "/static_pages" with the json variable page_post as the body
    And save the entity id as page
    And I send a GET request to the entity page
    Then the response status code should be 200
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "layout": {
          "type": "string"
        }
      }
    }
    """

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
