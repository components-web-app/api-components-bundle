Feature: A component cannot be placed inside its own subtree
  In order to keep the component tree a tree that renders and resolves
  As an API user
  I am refused any write that would place a component inside itself

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "nested" holding a component
    And the component "nested_component_0" has a ComponentGroup "deeper" holding a component

  @loginUser
  Scenario: A component cannot be placed in one of its own component groups
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                   | component           |
      | resource[nested_component_group] | resource[component] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "component"
    And the JSON node "violations[0].message" should be equal to "The component cannot be placed in the component group `nested`: the group is inside the component."

  @loginUser
  Scenario: A component cannot be placed in a component group further down its own subtree
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                   | component           |
      | resource[deeper_component_group] | resource[component] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "component"

  @loginUser
  Scenario: The same component can be placed in two sibling containers
    Given the Page "page" has a ComponentGroup "sibling" holding a component
    And the component "sibling_component_0" has a ComponentGroup "sibling_inner" holding a component
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                          | component                    |
      | resource[sibling_inner_component_group] | resource[nested_component_0] |
    Then the response status code should be 201

  @loginUser
  Scenario: A position cannot be changed to hold a component whose subtree holds the position
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "deeper_position_0" with data:
      | component           |
      | resource[component] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "component"

  @loginUser
  Scenario: A position cannot be moved into a component group inside the component it holds
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position" with data:
      | componentGroup                   |
      | resource[deeper_component_group] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "component"

  @loginUser
  Scenario: A component group cannot be given an owner from inside its own subtree
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "component_group" with data:
      | components                                  |
      | json_decode(["resource[nested_component_0]"]) |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "components"
    And the JSON node "violations[0].message" should be equal to "The component group `manifest-tag-group` cannot be owned by a component inside it."

  @loginUser
  Scenario: A component group can be given an owner from outside its subtree
    Given the Page "page" has a ComponentGroup "sibling" holding a component
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "deeper_component_group" with data:
      | components                                     |
      | json_decode(["resource[nested_component_0]", "resource[sibling_component_0]"]) |
    Then the response status code should be 200

  @loginUser
  Scenario: A component cannot be written with a position inside its own subtree
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "component" with data:
      | componentPositions                                                    |
      | json_decode([{"componentGroup": "resource[deeper_component_group]"}]) |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentPositions[0].component"

  @loginAdmin
  Scenario: A draft cannot be placed inside its published component's subtree
    Given there is a routed Page "/published-route" whose group holds a published DummyPublishableComponent with a draft
    And the component "published_component" has a ComponentGroup "published_nested" holding a component
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                             | component                 |
      | resource[published_nested_component_group] | resource[draft_component] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "component"

  Scenario: A component in a cycle already in the database can be read anonymously
    Given the ComponentGroup "deeper_component_group" holds the resource "component"
    When I send a "GET" request to the resource "nested_component_0"
    Then the response status code should be 200

  @loginAdmin
  Scenario: A component in a cycle already in the database can be read by an admin
    Given the ComponentGroup "deeper_component_group" holds the resource "component"
    When I send a "GET" request to the resource "nested_component_0"
    Then the response status code should be 200

  Scenario: The manifest of a page holding a cycle already in the database is built
    Given the ComponentGroup "deeper_component_group" holds the resource "component"
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest depth 0 should contain the IRI of the resource "deeper_component_group"

  @loginAdmin
  Scenario: A write inside a cycle already in the database purges the manifest
    Given the ComponentGroup "deeper_component_group" holds the resource "component"
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "deeper_component_group" with body:
    """
    {"location": "moved"}
    """
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be purged
