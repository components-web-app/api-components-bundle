Feature: Component positions
  In order to position components
  As an API user
  I can add a component into a collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario Outline: When I delete a component collection resource, locations are also deleted but components are not
    Given there is a ComponentCollection with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection   | component   |
      | <componentCollection> | <component> |
    Then the response status code should be 201
    Examples:
      | component                    | componentCollection             |
      | component[dummy_component]   | component[component_collection] |
