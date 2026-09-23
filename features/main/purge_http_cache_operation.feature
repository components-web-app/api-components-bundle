Feature: Flushing the whole HTTP cache on request
  In order to drop stale API responses and the pages rendered from them after a deploy or a data fix
  As an administrator
  I need an operation that flushes every cached response

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: A purger that cannot flush is refused rather than reporting success
    When I send a "POST" request to "/_/http_cache/purge"
    Then the response status code should be 501
    And no cache purge request should have been sent

  @loginAdmin
  Scenario: The operation cannot be reached with GET
    When I send a "GET" request to "/_/http_cache/purge"
    Then the response status code should be 405

  @loginUser
  Scenario: A user without the administrator role is refused by the operation's own security
    When I send a "POST" request to "/_/http_cache/purge"
    Then the response status code should be 403
    And no cache purge request should have been sent

  Scenario: An anonymous request is refused, by the test application's firewall before the operation is reached
    When I send a "POST" request to "/_/http_cache/purge"
    Then the response status code should be 401
