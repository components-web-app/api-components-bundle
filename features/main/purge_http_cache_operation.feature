Feature: Flushing the whole HTTP cache, or purging given paths and tags, on request
  In order to drop one stale page, or after a deploy or a data fix every stale API response and the pages rendered from them
  As an administrator
  I need an operation that purges the paths and tags in its body, or with no body flushes every cached response

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

  @loginAdmin
  Scenario: A body naming tags purges those tags and nothing else, and never flushes
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"tags": ["/_/layouts", "cwa-html"]}
    """
    Then the response status code should be 204
    And the response should be empty
    And the cache tags "/_/layouts cwa-html" should be the only cache tags purged

  @loginAdmin
  Scenario: A body naming paths purges the tag a write to the route at each path purges, exactly as the command's --path does
    Given there is a Route "/about-us/team" with a page
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"paths": ["/about-us/team", "/"]}
    """
    Then the response status code should be 204
    And the cache tags "/_/routes//about-us/team /_/routes//" should be the only cache tags purged

  @loginAdmin
  Scenario: Paths and tags can be sent together, and the union is purged once
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"paths": ["/about-us"], "tags": ["cwa-html", "/_/routes//about-us"]}
    """
    Then the response status code should be 204
    And the cache tags "/_/routes//about-us cwa-html" should be the only cache tags purged
    And the cache tag "/_/routes//about-us" should be purged 1 time

  @loginAdmin
  Scenario: The API routes import prefix is part of the tag a path resolves to
    Given the API routes are imported under the prefix "/_api"
    When I send a "POST" request to "/_api/_/http_cache/purge" with body:
    """
    {"paths": ["/about-us"]}
    """
    Then the response status code should be 204
    And "/_api/_/routes//about-us" should be the only cache tag purged

  @loginAdmin
  Scenario: A scoped purge that fails is a 502
    Given the HTTP cache is unreachable
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"tags": ["cwa-html"]}
    """
    Then the response status code should be 502
    And the header "Content-Type" should contain "application/problem+json"

  @loginAdmin
  Scenario Outline: A body that is not a scoped purge is refused and neither purges nor flushes
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    <body>
    """
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to the string "<propertyPath>"
    And no cache purge request should have been sent

    Examples:
      | body                                   | propertyPath |
      | {}                                     |              |
      | []                                     |              |
      | null                                   |              |
      | "/_/layouts"                           |              |
      | {"tags": ["cwa-html"]                  |              |
      | {"tag": ["cwa-html"]}                  | tag          |
      | {"tags": ["cwa-html"], "all": true}    | all          |
      | {"tags": []}                           | tags         |
      | {"paths": []}                          | paths        |
      | {"tags": "cwa-html"}                   | tags         |
      | {"tags": {"a": "cwa-html"}}            | tags         |
      | {"paths": null}                        | paths        |
      | {"tags": ["cwa-html", "/one,/two"]}    | tags[1]      |
      | {"tags": ["/one /two"]}                | tags[0]      |
      | {"tags": [""]}                         | tags[0]      |
      | {"tags": [1]}                          | tags[0]      |
      | {"paths": ["about-us"]}                | paths[0]     |
      | {"paths": ["/about-us", 5]}            | paths[1]     |
      | {"paths": ["/about-us"], "tags": [""]} | tags[0]      |

  @loginAdmin
  Scenario: A path whose route tag the purge header would split is refused before anything is purged
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"paths": ["/one,two"]}
    """
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to the string "paths[0]"
    And no cache purge request should have been sent

  @loginUser
  Scenario: A user without the administrator role cannot purge tags
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"tags": ["cwa-html"]}
    """
    Then the response status code should be 403
    And no cache purge request should have been sent

  @loginAdmin
  Scenario: A scoped purge sent as plain JSON is accepted
    Given I add "Content-Type" header equal to "application/json"
    When I send a "POST" request to "/_/http_cache/purge" with body:
    """
    {"tags": ["cwa-html"]}
    """
    Then the response status code should be 204
    And "cwa-html" should be the only cache tag purged
