Feature: A saved write is answered successfully when the HTTP cache cannot be purged
  In order not to tell a client that a saved write failed, and not to hide a purge that did not happen
  As an API user and an administrator
  I get the normal response to a write when the cache purge fails, the failure is logged, and an explicit purge reports its failure

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: Creating a resource while the HTTP cache is unreachable returns 201, logs the failure and still publishes to Mercure
    Given the HTTP cache is unreachable
    When I send a "POST" request to "/_/layouts" with body:
    """
    {
      "reference": "primary",
      "uiComponent": "PrimaryLayout"
    }
    """
    Then the response status code should be 201
    And the response resource should be saved as "layout"
    And the resource "layout" should exist
    And the cache purge failure for the resource "layout" should have been logged
    And a Mercure update should have been published for the resource "layout"

  @loginUser
  Scenario: The HTTP cache is reachable again in the next scenario
    When I send a "POST" request to "/_/layouts" with body:
    """
    {
      "reference": "secondary",
      "uiComponent": "PrimaryLayout"
    }
    """
    Then the response status code should be 201
    And the response resource should be saved as "layout"
    And the resource "layout" should be purged from the cache
    And no cache purge failure should have been logged

  @loginAdmin
  Scenario: Purging the rendered HTML while the HTTP cache is unreachable is a 502, not a success
    Given the HTTP cache is unreachable
    When I send a "POST" request to "/_/rendered_html/purge"
    Then the response status code should be 502
    And the header "Content-Type" should be equal to "application/problem+json"
