Feature: A delete leaves no orphans behind
  In order to delete everything the orphan report lists in one go
  As an administrator
  I need every delete to cascade far enough that the next scan finds nothing the delete caused

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: Deleting a group removes the groups owned by the components it cascades to
    Given there is a routed Page whose ComponentGroup holds a component that owns a group holding a component
    When I send a "DELETE" request to the resource "outer_group"
    Then the response status code should be 204
    And the resource "outer_component" should not exist
    And the resource "inner_group" should not exist
    And the resource "inner_component" should not exist
    When I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "componentGroups" should have 0 elements
    And the JSON node "componentPositions" should have 0 elements
    And the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: Deleting an orphaned published component removes its draft
    Given there is a published resource with a draft
    When I request the deletion of the orphaned resources "publishable_published"
    Then the response status code should be 200
    And the component "publishable_published" should not exist in the database
    And the component "publishable_draft" should not exist in the database
    When I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: A published component removed by a group cascade takes its draft with it
    Given there is an orphaned ComponentGroup
    And there is a published resource with a draft
    And the ComponentGroup "orphaned_component_group" holds the resource "publishable_published"
    When I send a "DELETE" request to the resource "orphaned_component_group"
    Then the response status code should be 204
    And the component "publishable_published" should not exist in the database
    And the component "publishable_draft" should not exist in the database
    When I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "componentGroups" should have 0 elements
    And the JSON node "componentPositions" should have 0 elements
    And the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: Deleting every orphan in bulk leaves nothing for the next scan
    Given there is a site with every kind of orphan and every kind of use
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 200
    When I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "componentGroups" should have 0 elements
    And the JSON node "componentPositions" should have 0 elements
    And the JSON node "components" should have 0 elements
    And the component "placed_published_draft" should exist in the database
    And the resource "page_data_component" should exist
    And the resource "parent_typed_component" should exist
