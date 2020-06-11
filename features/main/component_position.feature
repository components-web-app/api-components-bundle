Feature: Component positions
  In order to position components
  As an API user
  I can add a component into a collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: Create a new component position resource
    Given there is a ComponentCollection with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection             | component                  |
      | resource[component_collection] | resource[dummy_component] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_position.schema.json"

  @loginUser
  Scenario Outline: I can restrict which components are permitted to be inside a component collection and a component that must be specifically defined as being allowed to pass validation
    Given there is a ComponentCollection with 0 components
    And the ComponentCollection has the allowedComponent "<allowedComponent>"
    And there is a DummyComponent
    And there is a RestrictedComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection   | component   |
      | <componentCollection> | <component> |
    Then the response status code should be <status>
    Examples:
      | component                       | componentCollection             | status | allowedComponent                 |
      | resource[dummy_component]       | resource[component_collection]  | 400    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_collection]  | 201    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_collection]  | 400    |                                  |
      | resource[dummy_component]       | resource[component_collection]  | 201    |                                  |

  @loginUser
  Scenario: ComponentPosition sortValue auto-increments
    Given there is a ComponentCollection with 1 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection             | component              |
      | resource[component_collection] | resource[component_0] |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And the JSON should be valid according to the schema file "component_position.schema.json"

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentCollection with 3 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection             | component              | sortValue   |
      | resource[component_collection] | resource[component_0] | 1           |
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

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to "http://example.com/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
