Feature: A health endpoint that is never cached and shows the API can reach its database
  In order to probe an API instance for readiness without poisoning a shared cache
  As an orchestrator or a deploy script
  I can request an anonymous health check that is answered fresh on every request

  Scenario: An anonymous health check answers ok when the database is reachable
    When I send a "GET" request to "/_/health"
    Then the response status code should be 200
    And the header "Content-Type" should be equal to "application/json"
    And the JSON should be equal to:
    """
    {
      "status": "ok"
    }
    """

  Scenario: A health check response is never stored by any cache
    When I send a "GET" request to "/_/health"
    Then the response status code should be 200
    And the header "Cache-Control" should contain "no-store"
    And the header "Cache-Control" should not contain "public"
    And the header "Cache-Control" should not contain "max-age"

  Scenario: A health check response carries no cache tags
    When I send a "GET" request to "/_/health"
    Then the response status code should be 200
    And the header "xkey" should not exist
    And the header "Surrogate-Key" should not exist
    And the header "Cache-Tags" should not exist

  Scenario: A health check response sets no cookie
    When I send a "GET" request to "/_/health"
    Then the response status code should be 200
    And the header "Set-Cookie" should not exist

  Scenario: A health check can be made with HEAD
    When I send a "HEAD" request to "/_/health"
    Then the response status code should be 200
    And the header "Cache-Control" should contain "no-store"

  Scenario Outline: A health check only answers GET and HEAD
    When I send a "<method>" request to "/_/health"
    Then the response status code should be 405

    Examples:
      | method |
      | POST   |
      | PUT    |
      | PATCH  |
      | DELETE |

  Scenario: A health check answers 503 with a short reason when the database is unreachable
    Given the database is unreachable
    When I send a "GET" request to "/_/health"
    Then the response status code should be 503
    And the header "Cache-Control" should contain "no-store"
    And the header "Cache-Control" should not contain "public"
    And the JSON should be equal to:
    """
    {
      "status": "unavailable",
      "reason": "database"
    }
    """
    And the response should not contain "unreachable-database-test-double"

  Scenario: An unreachable database is logged with its cause
    Given the database is unreachable
    When I send a "GET" request to "/_/health"
    Then the response status code should be 503
    And the unreachable database should have been logged

  Scenario: The database is reachable again in the next scenario
    When I send a "GET" request to "/_/health"
    Then the response status code should be 200
