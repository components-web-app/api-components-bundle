Feature: A page's layout component groups are in its resource manifest
  In order to render a page's layout without a second round trip
  As a front end which loads a page from its manifest
  I need the layout's component groups, their positions and their components in the manifest, once, at the shallowest depth that uses the layout

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: The manifest lists the layout's component groups, positions and components at the page's depth
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Pages "page" use a Layout with a ComponentGroup holding a component
    When I send a "GET" request to the resource "page_manifest"
    Then the response status code should be 200
    And the manifest depth 1 should contain the IRI of the resource "shared_layout"
    And the manifest depth 1 should contain the IRI of the resource "layout_component_group"
    And the manifest depth 1 should contain the IRI of the resource "layout_position_0"
    And the manifest depth 1 should contain the IRI of the resource "layout_component_0"
    And the manifest depth 0 should not contain the IRI of the resource "layout_component_group"

  @loginAdmin
  Scenario: A layout shared by a parent and child page is listed once, at the shallowest depth
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Pages "parent_page, page" use a Layout with a ComponentGroup holding a component
    When I send a "GET" request to the resource "page_manifest"
    Then the response status code should be 200
    And the manifest depth 0 should contain the IRI of the resource "shared_layout"
    And the manifest depth 0 should contain the IRI of the resource "layout_component_group"
    And the manifest depth 0 should contain the IRI of the resource "layout_position_0"
    And the manifest depth 0 should contain the IRI of the resource "layout_component_0"
    And the manifest depth 1 should not contain the IRI of the resource "shared_layout"
    And the manifest depth 1 should not contain the IRI of the resource "layout_component_group"
    And the manifest depth 1 should not contain the IRI of the resource "layout_position_0"
    And the manifest depth 1 should not contain the IRI of the resource "layout_component_0"

  @loginUser
  Scenario: A layout's own response still lists its component groups as IRIs
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Pages "page" use a Layout with a ComponentGroup holding a component
    When I send a "GET" request to the resource "shared_layout"
    Then the response status code should be 200
    And the JSON node "componentGroups[0]" should be equal to the IRI of the resource "layout_component_group"
