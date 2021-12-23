Feature: Components
  In order to create a component
  As an API user
  I can add extend AbstractComponent

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: I can get a component's usage
    Given there is a DummyComponent in PageData and a Position
    When I send a "GET" request to the resource "dummy_component" and the postfix "/usage"
    Then the response status code should be 200
    And the JSON node "total" should be equal to 2
    And the JSON node "positionCount" should be equal to 1
    And the JSON node "pageDataCount" should be equal to 1

  @loginUser
  Scenario: I can create a component
    When I send a "POST" request to "/component/dummy_components" with body:
    """
    {
      "uiComponent": "AnotherComponent",
      "uiClassNames": ["my-class"]
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component.schema.json"
    And the JSON node "uiComponent" should be equal to "AnotherComponent"
    And the JSON node "uiClassNames[0]" should be equal to "my-class"

  @loginUser
  Scenario: I can create a component and the ComponentPosition at the same time
    Given there is a ComponentCollection with 0 components and the ID "41c57f7a-9f69-11ea-8188-acde48001122"
    When I send a "POST" request to "/component/dummy_components" with body:
    """
    {
      "componentPositions": [
        {
          "componentCollection": "/_/component_collections/41c57f7a-9f69-11ea-8188-acde48001122"
        }
      ]
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component.schema.json"
    And I save the JSON node "@id" as the resource "new_component"
    And I save the JSON node "componentPositions[0]" as the resource "new_component_position"
    Then I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "new_component_position"
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "componentCollection" should be equal to the string "/_/component_collections/41c57f7a-9f69-11ea-8188-acde48001122"
    And the JSON node "component" should be equal to the IRI of the resource "new_component"

  @loginUser
  Scenario: I can delete a component
    Given there is a DummyComponent
    When I send a "DELETE" request to the resource "dummy_component"
    Then the response status code should be 204
