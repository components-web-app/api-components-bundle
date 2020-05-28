Feature: Components
  In order to create a component
  As an API user
  I can add extend AbstractComponent

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a component
    When I send a "POST" request to "/component/dummy_components" with body:
    """
    {}
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component.schema.json"

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
    And I save the JSON node "@id" as the component "new_component"
    And I save the JSON node "componentPositions[0]" as the component "new_component_position"
    Then I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "new_component_position"
    And the JSON node "componentCollection" should be equal to the string "/_/component_collections/41c57f7a-9f69-11ea-8188-acde48001122"
    And the JSON node "component" should be equal to the IRI of the resource "new_component"

  @loginUser
  Scenario: When I delete a component
    Given there is a DummyComponent
    When I send a "DELETE" request to the resource "dummy_component"
    Then the response status code should be 204

  @wip
  Scenario: I can can configure the component so it must be specifically allowed within a component group to be able to be added to it
