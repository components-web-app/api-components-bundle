Feature: Deleting orphaned resources
  In order to clean up what nothing uses any more
  As an administrator
  I need to delete selected orphans, or all of them, checked against a fresh scan and cascaded like an API delete

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And there is a site with every kind of orphan and every kind of use

  @loginAdmin
  Scenario: Deleting selected orphans deletes only those, each with its cascade
    When I request the deletion of the orphaned resources "orphaned_group, unused_published, owning_component"
    Then the response status code should be 200
    And the JSON node "deleted.componentGroups" should list exactly the resources "orphaned_group, owned_group"
    And the JSON node "deleted.componentPositions" should list exactly the resources "orphaned_group_position, orphaned_group_empty_position, owned_position"
    And the JSON node "deleted.components" should list exactly the resources "orphaned_group_component, unused_published, unused_published_draft, owning_component, owned_component"
    And the JSON node "rejected" should have 0 elements
    And the resource "orphaned_group" should not exist
    And the component "unused_published_draft" should not exist in the database
    And the resource "owned_component" should not exist
    And the resource "unused_component" should exist
    And the resource "empty_position" should exist

  @loginAdmin
  Scenario: Deleting all orphans deletes everything reported, with its cascade, and nothing in use
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 200
    And the JSON node "deleted.componentGroups" should list exactly the resources "orphaned_group, owned_group"
    And the JSON node "deleted.componentPositions" should list exactly the resources "empty_position, orphaned_group_position, orphaned_group_empty_position, owned_position"
    And the JSON node "deleted.components" should list exactly the resources "unused_component, orphaned_group_component, unused_published, unused_published_draft, owning_component, owned_component"
    And the JSON node "rejected" should have 0 elements
    And the resource "page_group" should exist
    And the resource "placed_position" should exist
    And the resource "placed_component" should exist
    And the resource "placed_published" should exist
    And the component "placed_published_draft" should exist in the database
    And the resource "page_data_component" should exist
    And the resource "parent_typed_component" should exist

  @loginAdmin
  Scenario: A deletion purges the deleted resources from the cache
    When I request the deletion of the orphaned resources "orphaned_group"
    Then the response status code should be 200
    And the resource "orphaned_group" should be purged from the cache
    And the resource "orphaned_group_component" should be purged from the cache

  @loginAdmin
  Scenario: A resource in use is rejected and kept
    When I request the deletion of the orphaned resources "placed_component, placed_published_draft, unused_component"
    Then the response status code should be 200
    And the JSON node "deleted.components" should list exactly the resources "unused_component"
    And the JSON node "rejected" should have 2 elements
    And the JSON node "rejected[0].iri" should be equal to the IRI of the resource "placed_component"
    And the JSON node "rejected[0].reason" should be equal to "not_orphaned"
    And the JSON node "rejected[1].iri" should be equal to the IRI of the resource "placed_published_draft"
    And the JSON node "rejected[1].reason" should be equal to "not_orphaned"
    And the resource "placed_component" should exist
    And the component "placed_published_draft" should exist in the database

  @loginAdmin
  Scenario: An IRI that no longer exists or is not a resource is rejected cleanly
    Given the resource "unused_component" has been removed from the database
    When I request the deletion of the orphaned resources "unused_component, /not/a/resource"
    Then the response status code should be 200
    And the JSON node "deleted.components" should have 0 elements
    And the JSON node "rejected[0].iri" should be equal to the IRI of the resource "unused_component"
    And the JSON node "rejected[0].reason" should be equal to "not_found"
    And the JSON node "rejected[1].iri" should be equal to "/not/a/resource"
    And the JSON node "rejected[1].reason" should be equal to "not_found"

  @loginAdmin
  Scenario: The stored report no longer lists what was deleted
    When I request the deletion of the orphaned resources "unused_component, orphaned_group"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the JSON node "componentGroups" should list exactly the resources "owned_group"
    And the JSON node "componentPositions" should list exactly the resources "empty_position, owned_position"
    And the JSON node "components" should list exactly the resources "unused_published, owning_component, owned_component"

  @loginAdmin
  Scenario Outline: A request must name its IRIs or ask for all, but not both
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
      """
      <body>
      """
    Then the response status code should be 422
    And the resource "unused_component" should exist
    Examples:
      | body                          |
      | {}                            |
      | {"all": false}                |
      | {"all": true, "iris": []}     |

  @loginAdmin
  Scenario: The delete operation cannot be requested with GET
    When I send a "GET" request to "/_/orphaned_resources/delete"
    Then the response status code should be 405

  @loginUser
  Scenario: A user without the administrator role cannot delete orphans
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 403
    And the resource "unused_component" should exist

  Scenario: An anonymous request is refused, by the test application's firewall before the operation is reached
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 401
    And the resource "unused_component" should exist
