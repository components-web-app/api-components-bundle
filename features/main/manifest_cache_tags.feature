Feature: Manifest cache tags
  In order for a cached resource manifest to be dropped exactly when its membership changes
  As a front end which renders a page from a manifest
  I need a manifest response to carry one grouping key per rendering depth instead of every
  resource IRI it lists, and a write to a structural resource to purge those grouping keys

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A manifest response carries a grouping key for the page it renders and none of its members
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be in the response
    And the cache tag for the resource "route" should not be in the response
    And the cache tag for the resource "layout" should not be in the response
    And the cache tag for the resource "component_group" should not be in the response
    And the cache tag for the resource "component" should not be in the response

  Scenario: A manifest addressed by UUID carries the same grouping key as one addressed by route path
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "GET" request to the resource "page_manifest"
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be in the response

  Scenario: A nested manifest carries a grouping key for every rendering depth
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    When I send a "GET" request to "/_/resource_manifest//conference/programme"
    Then the response status code should be 200
    And the manifest cache tag for the resource "page_data" should be in the response
    And the manifest cache tag for the resource "parent_page_data" should be in the response

  Scenario: A page data manifest carries a grouping key for its template page as well as its own
    Given there is a PageData resource with the route path "/my-route"
    When I send a "GET" request to "/_/resource_manifest//my-route"
    Then the response status code should be 200
    And the manifest cache tag for the resource "page_data" should be in the response
    And the manifest cache tag for the resource "page_data_page" should be in the response

  @loginAdmin
  Scenario: Editing a component group inside a template page purges the manifest of the page data built from it
    Given there is a PageData resource with the route path "/my-route"
    When I send a "PATCH" request to the resource "page_data_component_group" with data:
      | location     |
      | new-location |
    Then the response status code should be 200
    And the manifest cache tag for the resource "page_data_page" should be purged

  Scenario: An ordinary resource response is still tagged with its own IRI
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the cache tag for the resource "component_group" should be in the response

  @loginAdmin
  Scenario: Adding a component to a group purges the manifest of the page the group is in
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201
    And the manifest cache tag for the resource "page" should be purged

  @loginAdmin
  Scenario: Adding a position to a layout's component group purges the manifest of a page using the layout
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the Pages "page" use a Layout with a ComponentGroup holding a component
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                   | component                 |
      | resource[layout_component_group] | resource[dummy_component] |
    Then the response status code should be 201
    And the manifest cache tag for the resource "page" should be purged

  @loginAdmin
  Scenario: Adding a position to a component's own component group purges the manifest of the page the component is in
    Given there is a routed Page with a component group and a component with the path "/my-route"
    And the component "component" has a ComponentGroup "nested" holding a component
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                   | component                 |
      | resource[nested_component_group] | resource[dummy_component] |
    Then the response status code should be 201
    And the manifest cache tag for the resource "page" should be purged

  @loginAdmin
  Scenario: Editing a component's content does not purge the manifest of the page it is in
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "PATCH" request to the resource "component" with data:
      | uiComponent          |
      | AnotherTestComponent |
    Then the response status code should be 200
    And the resource "component" should be purged from the cache
    And the manifest cache tag for the resource "page" should not be purged

  @loginAdmin
  Scenario: Editing a page purges its own manifest
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "PATCH" request to the resource "page" with data:
      | title     |
      | New title |
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be purged

  @loginAdmin
  Scenario: Editing a layout purges the manifest of every page built from it
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "PATCH" request to the resource "layout" with data:
      | uiComponent      |
      | CwaLayoutUpdated |
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be purged

  @loginAdmin
  Scenario: Editing a route purges the manifest of the page it publishes
    Given there is a routed Page with a component group and a component with the path "/my-route"
    When I send a "PATCH" request to the resource "route" with data:
      | path          |
      | /another-path |
    Then the response status code should be 200
    And the manifest cache tag for the resource "page" should be purged
