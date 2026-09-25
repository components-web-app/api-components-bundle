Feature: Reporting orphaned component groups, positions and components
  In order to find structural resources nothing uses any more
  As an administrator
  I need to request a scan and then fetch the report it stored

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: A scan reports an orphaned group, an empty position and an unused component
    Given there is a routed Page with a component group and a component with the path "/clean"
    And there is an orphaned ComponentGroup
    And the Page "page" has a ComponentPosition with neither a component nor a page data property
    And there is a DummyComponent
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the JSON node "generatedAt" should be now
    And the JSON node "componentGroups" should list exactly the resources "orphaned_component_group"
    And the JSON node "componentPositions" should list exactly the resources "empty_position"
    And the JSON node "components" should list exactly the resources "dummy_component"

  @loginAdmin
  Scenario: A draft and a component used only through a page data property are not reported
    Given there is a routed Page with a component group and a component with the path "/clean"
    And there is a published resource with a draft
    And the Page "page" holds the resource "publishable_published"
    And there is a PageData resource with the route path "/page-data"
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the JSON node "componentGroups" should have 0 elements
    And the JSON node "componentPositions" should have 0 elements
    And the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: A published component that nothing uses is reported, but not its draft
    Given there is a published resource with a draft
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    When I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "components" should list exactly the resources "publishable_published"

  @loginAdmin
  Scenario: The report is never stored by a shared cache, because a later scan is not a write that could purge it
    Given I send a "POST" request to "/_/orphaned_resources/scan"
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the header "Cache-Control" should contain "private"
    And the header "Cache-Control" should contain "no-store"
    And the header "Cache-Control" should not contain "s-maxage"

  @loginAdmin
  Scenario: A clean site gives an empty report
    Given there is a routed Page with a component group and a component with the path "/clean"
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the JSON node "componentGroups" should have 0 elements
    And the JSON node "componentPositions" should have 0 elements
    And the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: A later scan replaces the stored report
    Given there is a DummyComponent
    When I send a "POST" request to "/_/orphaned_resources/scan"
    And the resource "dummy_component" has been removed from the database
    And I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the JSON node "components" should have 0 elements

  @loginAdmin
  Scenario: Fetching the report before any scan has run is a 404
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 404

  @loginAdmin
  Scenario: The scan cannot be requested with GET
    When I send a "GET" request to "/_/orphaned_resources/scan"
    Then the response status code should be 405

  @loginUser
  Scenario: A user without the administrator role cannot request a scan
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 403
    And no orphaned resources report should have been stored

  @loginUser
  Scenario: A user without the administrator role cannot fetch the report
    Given an orphaned resources report has been stored
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 403

  Scenario: An anonymous request for a scan is refused, by the test application's firewall before the operation is reached
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 401
    And no orphaned resources report should have been stored

  Scenario: An anonymous request for the report is refused by the operation's own security
    Given an orphaned resources report has been stored
    When I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 401
