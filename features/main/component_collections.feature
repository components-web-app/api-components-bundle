Feature: ComponentCollection resource
  In order to have collections of components
  As an API user
  I can add components to a component collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I cannot add a component collection without a reference
    When I send a "POST" request to "/_/component_collections" with body:
    """
    {
      "reference": ""
    }
    """
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"

  @loginUser
  Scenario: I can create a component collection resource
    When I send a "POST" request to "/_/component_collections" with body:
    """
    {
      "reference": "collection_reference"
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_collection.schema.json"

  @loginUser
  Scenario: When I delete a component collection resource, locations are also deleted but components are not
    Given there is a ComponentCollection with 4 components
    When I send a "DELETE" request to the component "component_collection"
    Then the response status code should be 204
    And there should be 4 DummyComponent resources
    And there should be 0 ComponentPosition resources

  Scenario: Components are ordered by the sortValue of the ComponentPosition
    Given there is a ComponentCollection with 4 components
    When I send a "GET" request to the component "component_collection"
    Then the response status code should be 200
    And the JSON node "componentPositions[0]" should be equal to the IRI of the component "position_0"
    And the JSON node "componentPositions[1]" should be equal to the IRI of the component "position_1"
    And the JSON node "componentPositions[2]" should be equal to the IRI of the component "position_2"
    And the JSON node "componentPositions[3]" should be equal to the IRI of the component "position_3"
