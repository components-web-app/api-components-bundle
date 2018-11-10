Feature: Components
  In order to manage components
  As an API user
  Properties and cascade operations are correctly configured

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Post a new component for the website
    When I send a POST request to "/contents" with body:
    """
    {
      "className": "custom-class",
      "content": ""
    }
    """
    Then the response status code should be 201
    And save the entity id as component
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/components/abstract.json"
    And the JSON node className should be equal to the string "custom-class"

  Scenario: I want a page for my component
    When I send a POST request to "/pages" with body:
    """
    {
      "title": "-",
      "metaDescription": "-"
    }
    """
    Then save the entity id as page

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

  Scenario: I want to get components when I get a page/content
    When I send a GET request to the entity page
    Then the response status code should be 200
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "componentLocations": {
          "type": "array",
          "minItems": 1,
          "required": true,
          "items": {
            "type": "object",
            "properties": {
              "component": {
                "type": "object",
                "required": true,
                "properties": {
                  "className": {
                    "type": "string",
                    "required": true
                  }
                }
              }
            }
          }
        }
      }
    }
    """

  Scenario: I want to delete a the location
    When I send a DELETE request to the entity component_location
    Then the response status code should be 204

  Scenario: I want to get the component again even though there is no location
    When I send a GET request to the entity component
    Then the response status code should be 200

  @dropSchema
  Scenario: I want to get the content/page again even though there is no location
    When I send a GET request to the entity page
    Then the response status code should be 200
