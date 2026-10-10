Feature: Purging selected tags or one page from the HTTP cache with the console command
  In order to drop one stale page without flushing the whole cache, and reach a CDN that only purges by tag
  As an operator with a shell in the API pod
  I need the HTTP cache purge command to purge given tags, or the tag a route write purges for a path, through the configured purger

  Background:
    Given the API routes are imported under the prefix "/_api"

  Scenario: Each tag option is purged and nothing else is
    When I run the HTTP cache purge command with:
      | option | value                    |
      | --tag  | /_api/_/routes//about-us |
      | --tag  | cwa-html                 |
    Then the HTTP cache purge command should have exited with 0
    And the cache tags "/_api/_/routes//about-us cwa-html" should be the only cache tags purged
    And the HTTP cache purge command output should contain "/_api/_/routes//about-us"
    And the HTTP cache purge command output should contain "cwa-html"

  Scenario: A path purges the tag a write to the route at that path purges, with the route import prefix, when no route exists there
    When I run the HTTP cache purge command with:
      | option | value     |
      | --path | /about-us |
    Then the HTTP cache purge command should have exited with 0
    And "/_api/_/routes//about-us" should be the only cache tag purged
    And the HTTP cache purge command output should contain "/_api/_/routes//about-us"

  Scenario: A path purges the same tag when a route exists there, and a nested path keeps all its segments
    Given there is a Route "/about-us/team" with a page
    When I run the HTTP cache purge command with:
      | option | value          |
      | --path | /about-us/team |
      | --path | /              |
    Then the HTTP cache purge command should have exited with 0
    And the cache tags "/_api/_/routes//about-us/team /_api/_/routes//" should be the only cache tags purged

  Scenario: A purge that fails is reported and exits 1
    Given the HTTP cache is unreachable
    When I run the HTTP cache purge command with:
      | option | value     |
      | --path | /about-us |
    Then the HTTP cache purge command should have exited with 1
    And the HTTP cache purge command output should contain "Failed to purge the HTTP cache tags"
    And the HTTP cache purge command output should contain "Could not resolve host"

  Scenario: A tag that the purge header would split is refused before anything is purged
    When I run the HTTP cache purge command with:
      | option | value     |
      | --tag  | /one,/two |
    Then the HTTP cache purge command should have exited with 2
    And no cache purge request should have been sent
