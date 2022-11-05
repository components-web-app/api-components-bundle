Feature: Component positions
  In order to position components
  As an API user
  I can add a component into a collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: Create a new component position resource
    Given there is a ComponentGroup with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup             | component                  |
      | resource[component_group]  | resource[dummy_component] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the Mercure message for component group should contain timestamped fields
    And the Mercure message for component group should contain 1 component position

  @loginUser
  Scenario Outline: I can restrict which components are permitted to be inside a component collection and a component that must be specifically defined as being allowed to pass validation
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has the allowedComponent "<allowedComponent>"
    And there is a DummyComponent
    And there is a RestrictedComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup   | component   |
      | <componentGroup> | <component> |
    Then the response status code should be <status>
    Examples:
      | component                       | componentGroup                  | status | allowedComponent                 |
      | resource[dummy_component]       | resource[component_group]  | 422    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_group]  | 201    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_group]  | 422    |                                  |
      | resource[dummy_component]       | resource[component_group]  | 201    |                                  |

  @loginUser
  Scenario: ComponentPosition sortValue auto-increments
    Given there is a ComponentGroup with 1 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                 | component              |
      | resource[component_group] | resource[component_0] |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And the JSON should be valid according to the schema file "component_position.schema.json"

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 3 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 1           |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_2"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 4 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_2" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 3           |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_3"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 4 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_2" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 1           |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_3"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3
