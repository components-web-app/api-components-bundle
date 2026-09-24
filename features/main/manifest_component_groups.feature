Feature: Component groups owned by a component are in the resource manifest
  In order to render a component's own groups without another round trip
  As a front end which loads a page from its manifest
  I need a component's component groups, their positions and their components in the manifest at the component's depth

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: The manifest lists a component's own component groups, positions and components
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "nested" holding a component
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest depth 0 should contain the IRI of the resource "component"
    And the manifest depth 0 should contain the IRI of the resource "nested_component_group"
    And the manifest depth 0 should contain the IRI of the resource "nested_position_0"
    And the manifest depth 0 should contain the IRI of the resource "nested_component_0"

  Scenario: The manifest follows component groups nested more than one level deep
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "nested" holding a component
    And the component "nested_component_0" has a ComponentGroup "deeper" holding a component
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest depth 0 should contain the IRI of the resource "deeper_component_group"
    And the manifest depth 0 should contain the IRI of the resource "deeper_position_0"
    And the manifest depth 0 should contain the IRI of the resource "deeper_component_0"

  @loginAdmin
  Scenario: A component's groups on a child page are listed at the child's depth only
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Page "page" has a ComponentGroup "child" holding a component
    And the component "child_component_0" has a ComponentGroup "nested" holding a component
    When I send a "GET" request to the resource "page_manifest"
    Then the response status code should be 200
    And the manifest depth 1 should contain the IRI of the resource "nested_component_group"
    And the manifest depth 1 should contain the IRI of the resource "nested_position_0"
    And the manifest depth 1 should contain the IRI of the resource "nested_component_0"
    And the manifest depth 0 should not contain the IRI of the resource "nested_component_group"
    And the manifest depth 0 should not contain the IRI of the resource "nested_component_0"

  Scenario: A component placed inside its own component group does not stop the manifest
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "loop" which holds the component itself
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest depth 0 should contain the IRI of the resource "loop_component_group"
    And the manifest depth 0 should contain the IRI of the resource "loop_position"

  Scenario: A component's own response still lists its component groups as IRIs, not embedded
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "nested" holding a component
    When I send a "GET" request to the resource "component"
    Then the response status code should be 200
    And the JSON node "componentGroups[0]" should be equal to the IRI of the resource "nested_component_group"
