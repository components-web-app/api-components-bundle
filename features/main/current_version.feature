Feature: In order to detect the current API Components Bundle Version
  As an API User
  I should have this information when fetching the API metadata

# May not be best practice really, see: https://github.com/api-platform/core/pull/3810
  Scenario Outline: I can detect the API version
    Given I add "Accept" header equal to "<header>"
    And I add "Content-Type" header equal to "<header>"
    When I send a "GET" request to "/docs.<ext>"
    Then the response status code should be 200
    And the JSON node "info.version" should match the regex '/^1\.0\.0 \(dev-master@[a-zA-Z0-9]+\)$/'
    Examples:
      | header              | ext    |
      | application/json    | json   |
      | application/ld+json | jsonld |
