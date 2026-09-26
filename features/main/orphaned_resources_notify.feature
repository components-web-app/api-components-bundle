Feature: Emailing admins when the orphaned resource report changes
  In order to hear about orphaned resources without watching the admin page
  As an admin
  I need the scheduled scan command to email me when its result differs from what I was last alerted about

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A first report with orphans is emailed to every recipient with the counts, the new orphans and a link to the admin page
    Given there is a site with every kind of orphan and every kind of use
    And links in user emails default to the origin "https://admin.website.com"
    When I run the scheduled orphaned resources scan
    Then the console command should have exited with 0
    And the console command output should contain "The report has changed: a notification was sent."
    And an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should count 1 component group, 2 component positions and 3 components
    And the orphaned resources notification should list as new the resources "orphaned_group, empty_position, orphaned_group_empty_position, unused_component, unused_published, owning_component"
    And the orphaned resources notification should link to "https://admin.website.com/_cwa/orphaned"

  Scenario: A first report with no orphans sends nothing
    When I run the scheduled orphaned resources scan
    Then the console command should have exited with 0
    And the console command output should contain "The report has not changed: no notification was sent."
    And no orphaned resources notification should have been sent

  Scenario: A scan whose result matches the last alert sends nothing
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    When I run the scheduled orphaned resources scan
    Then the console command should have exited with 0
    And the console command output should contain "The report has not changed: no notification was sent."
    And no orphaned resources notification should have been sent

  Scenario: A first empty result sends nothing, so the next orphan is alerted on its own
    Given I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_component_group"
    And the orphaned resources notification should report 0 resources no longer orphaned

  Scenario: A scan that finds a new orphan lists only what is new since the last alert
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should count 2 component groups, 2 component positions and 3 components
    And the orphaned resources notification should list as new the resources "orphaned_component_group"

  @loginAdmin
  Scenario: A scheduled scan alerts on orphans an admin's HTTP scan found first
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    And I send a "POST" request to "/_/orphaned_resources/scan"
    And the response status code should be 202
    When I run the scheduled orphaned resources scan
    Then the console command output should contain "The report has changed: a notification was sent."
    And an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_component_group"

  @loginAdmin
  Scenario: The first scheduled scan after an admin's HTTP scan alerts on every orphan
    Given there is a site with every kind of orphan and every kind of use
    And I send a "POST" request to "/_/orphaned_resources/scan"
    And the response status code should be 202
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_group, empty_position, orphaned_group_empty_position, unused_component, unused_published, owning_component"

  @loginAdmin
  Scenario: A scheduled scan alerts on orphans the refresh after a bulk delete found first
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    And I request the deletion of the orphaned resources "unused_component"
    And the response status code should be 200
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_component_group"
    And the orphaned resources notification should report 1 resource no longer orphaned

  @loginAdmin
  Scenario: A result back at the last alert sends nothing, even when an admin's scan stored something else in between
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    And I send a "POST" request to "/_/orphaned_resources/scan"
    And I send a "DELETE" request to the resource "orphaned_component_group"
    And the response status code should be 204
    When I run the scheduled orphaned resources scan
    Then the console command output should contain "The report has not changed: no notification was sent."
    And no orphaned resources notification should have been sent

  Scenario: --no-notify stores the report and sends nothing
    Given there is a site with every kind of orphan and every kind of use
    When I run the scheduled orphaned resources scan with "--no-notify"
    Then the console command should have exited with 0
    And no orphaned resources notification should have been sent
    And the orphaned resources report should be stored in the database

  Scenario: --no-notify leaves the last alert alone, so the next scan alerts on the orphans it skipped
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan
    And there is an orphaned ComponentGroup
    And I run the scheduled orphaned resources scan with "--no-notify"
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_component_group"

  Scenario: --no-notify on a first scan leaves no last alert, so the next scan alerts on every orphan
    Given there is a site with every kind of orphan and every kind of use
    And I run the scheduled orphaned resources scan with "--no-notify"
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_group, empty_position, orphaned_group_empty_position, unused_component, unused_published, owning_component"

  Scenario: With no recipients configured nothing is sent
    Given there is a site with every kind of orphan and every kind of use
    And no recipients are configured for orphaned resources notifications
    When I run the scheduled orphaned resources scan
    Then the console command should have exited with 0
    And no orphaned resources notification should have been sent

  Scenario: Without a default origin the email is sent without a link and the missing link is logged
    Given there is a site with every kind of orphan and every kind of use
    When I run the scheduled orphaned resources scan
    Then an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should not link to the admin page
    And a missing link in the orphaned resources notification should have been logged

  Scenario: A failed send keeps the new report, is logged, and the command still succeeds
    Given there is a site with every kind of orphan and every kind of use
    And the mailer is unreachable
    When I run the scheduled orphaned resources scan
    Then the console command should have exited with 0
    And the console command output should contain "The report has changed, but the notification could not be sent. The error has been logged."
    And the failed orphaned resources notification should have been logged
    And the orphaned resources report should be stored in the database

  Scenario: A failed send leaves the last alert alone, so the next scan emails the same orphans again
    Given there is a site with every kind of orphan and every kind of use
    And the mailer is unreachable
    And I run the scheduled orphaned resources scan
    And the mailer is reachable again
    When I run the scheduled orphaned resources scan
    Then the console command output should contain "The report has changed: a notification was sent."
    And an orphaned resources notification should have been sent to "admin@website.com, webmaster@website.com"
    And the orphaned resources notification should list as new the resources "orphaned_group, empty_position, orphaned_group_empty_position, unused_component, unused_published, owning_component"

  @loginAdmin
  Scenario: The HTTP scan never sends a notification
    Given there is a site with every kind of orphan and every kind of use
    When I send a "POST" request to "/_/orphaned_resources/scan"
    Then the response status code should be 202
    And I should not receive any emails

  @loginAdmin
  Scenario: Refreshing the report after a bulk delete never sends a notification
    Given there is a site with every kind of orphan and every kind of use
    When I send a "POST" request to "/_/orphaned_resources/delete" with body:
    """
    {"all": true}
    """
    Then the response status code should be 200
    And I should not receive any emails
