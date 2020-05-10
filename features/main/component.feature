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
  Scenario: When I delete a component
    Given there is a DummyComponent
    When I send a "DELETE" request to the component "dummy_component"
    Then the response status code should be 204

  @loginUser
  Scenario: I can add a component to a component collection.

  @wip
  Scenario: I can can configure the component so it must be specifically allowed within a component group to be able to be added to it
