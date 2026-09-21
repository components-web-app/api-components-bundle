Feature: Purging the front end's rendered HTML on request
  In order for a front-end deploy or a manual purge to drop every cached page
  As an administrator
  I need an operation that purges the rendered HTML cache tag and nothing else

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: An administrator purges the rendered HTML tag and only that tag
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 204
    And the cache tag "cwa-html" should be purged 1 time
    And "cwa-html" should be the only cache tag purged

  @loginAdmin
  Scenario: A request body naming other tags cannot widen the purge
    When I send a "POST" request to "/_/rendered_html/purge" with body:
    """
    {"tags": ["/_/layouts", "manifest:/_/pages/1"], "tag": "/_/routes"}
    """
    Then the response status code should be 204
    And "cwa-html" should be the only cache tag purged

  @loginAdmin
  Scenario: A query string naming other tags cannot widen the purge
    When I send a "POST" request to "/_/rendered_html/purge?tags[]=/_/layouts&tags[]=manifest:/_/pages/1&tag=/_/routes"
    Then the response status code should be 204
    And "cwa-html" should be the only cache tag purged

  @loginAdmin
  Scenario: The operation cannot be reached with GET
    When I send a "GET" request to "/_/rendered_html/purge"
    Then the response status code should be 405
    And the cache tag "cwa-html" should not be purged

  @loginUser
  Scenario: A user without the administrator role is refused by the operation's own security
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 403
    And the cache tag "cwa-html" should not be purged

  Scenario: An anonymous request is refused, by the test application's firewall before the operation is reached
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 401
    And the cache tag "cwa-html" should not be purged

  @loginAdmin
  Scenario: Repeating the purge is safe and each call purges once
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 204
    And the cache tag "cwa-html" should be purged 1 time
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 204
    And the cache tag "cwa-html" should be purged 1 time
    And "cwa-html" should be the only cache tag purged
