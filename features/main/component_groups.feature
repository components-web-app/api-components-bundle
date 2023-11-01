Feature: ComponentGroup resource
  In order to have collections of components
  As an API user
  I can add components to a component collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I cannot add a component collection without a reference
    When I send a "POST" request to "/_/component_groups" with body:
    """
    {
      "reference": ""
    }
    """
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginUser
  Scenario: I can create a component collection resource
    When I send a "POST" request to "/_/component_groups" with body:
    """
    {
      "reference": "collection_reference",
      "location": "collection_location"
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_group.schema.json"
    And the JSON node "createdAt" should exist

  @loginUser
  Scenario: When I delete a component collection resource, locations are also deleted but components are not
    Given there is a ComponentGroup with 4 components
    When I send a "DELETE" request to the resource "component_group"
    Then the response status code should be 204
    And there should be 4 DummyComponent resources
    And there should be 0 ComponentPosition resources

  Scenario: Components are ordered by the sortValue of the resourcePosition
    Given there is a ComponentGroup with 4 components
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the JSON node "componentPositions[0]" should be equal to the IRI of the resource "position_0"
    And the JSON node "componentPositions[1]" should be equal to the IRI of the resource "position_1"
    And the JSON node "componentPositions[2]" should be equal to the IRI of the resource "position_2"
    And the JSON node "componentPositions[3]" should be equal to the IRI of the resource "position_3"

  @loginAdmin
  Scenario: I can add a component collection to pages and layouts
    Given there is a Page
    And there is a Layout
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_groups" with data:
     | reference | location  | pages                             | layouts                             | components                                   |
     | main_body | main      | json_decode([ "resource[page]" ]) | json_decode([ "resource[layout]" ]) | json_decode([ "resource[dummy_component]" ]) |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_group.schema.json"
    And the JSON node "pages[0]" should be equal to the IRI of the resource "page"
    And the JSON node "layouts[0]" should be equal to the IRI of the resource "layout"
    And the JSON node "components[0]" should be equal to the IRI of the resource "dummy_component"
