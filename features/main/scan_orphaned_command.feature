Feature: Scanning for orphaned resources from the console
  In order to refresh the orphaned resource report on a schedule
  As an operator
  I need a console command that scans and stores the report and never deletes anything

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: The scan command prints the counts per kind, lists nothing without -v and deletes nothing
    Given there is a site with every kind of orphan and every kind of use
    And I count the component groups, positions and components
    When I run the console command "silverback:api-components:scan-orphaned"
    Then the console command should have exited with 0
    And the console command output should contain "Component groups: 1"
    And the console command output should contain "Component positions: 2"
    And the console command output should contain "Components: 3"
    And the console command output should not contain the IRI of the resource "unused_component"
    And the component group, position and component counts should be unchanged

  Scenario: With -v the scan command lists exactly what the detector reports
    Given there is a site with every kind of orphan and every kind of use
    When I run the console command "silverback:api-components:scan-orphaned" verbosely
    Then the console command should have exited with 0
    And the console command output should list exactly the resources "orphaned_group" under "Component groups"
    And the console command output should list exactly the resources "empty_position, orphaned_group_empty_position" under "Component positions"
    And the console command output should list exactly the resources "unused_component, unused_published, owning_component" under "Components"

  Scenario: clean-orphaned is an alias of the scan command and deletes nothing
    Given there is a site with every kind of orphan and every kind of use
    And I count the component groups, positions and components
    When I run the console command "silverback:api-components:clean-orphaned" verbosely
    Then the console command should have exited with 0
    And the console command output should list exactly the resources "unused_component, unused_published, owning_component" under "Components"
    And the component group, position and component counts should be unchanged

  @loginAdmin
  Scenario: The report stored by the scan command is the one the API returns
    Given there is a site with every kind of orphan and every kind of use
    When I run the console command "silverback:api-components:scan-orphaned"
    And I send a "GET" request to "/_/orphaned_resources"
    Then the response status code should be 200
    And the JSON node "componentGroups" should list exactly the resources "orphaned_group"
    And the JSON node "componentPositions" should list exactly the resources "empty_position, orphaned_group_empty_position"
    And the JSON node "components" should list exactly the resources "unused_component, unused_published, owning_component"

  @loginAdmin
  Scenario: The scan command stores the same report as the HTTP scan
    Given there is a site with every kind of orphan and every kind of use
    When I run the console command "silverback:api-components:scan-orphaned"
    And I remember the stored orphaned resources report
    And I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    And the stored orphaned resources report should list the same resources as the remembered one
