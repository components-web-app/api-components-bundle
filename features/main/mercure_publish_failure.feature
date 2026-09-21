Feature: A saved write is answered successfully when Mercure cannot be reached
  In order not to tell a client that a saved write failed
  As an API user
  I get the normal response when the Mercure hub is unreachable, and the failure is logged

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: Creating a resource while the Mercure hub is unreachable returns 201 and logs the failure
    Given the Mercure hub is unreachable
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
    And the Mercure publish failure for the resource "layout" should have been logged

  @loginUser
  Scenario: The Mercure hub is reachable again in the next scenario
    When I send a "POST" request to "/_/layouts" with body:
    """
    {
      "reference": "secondary",
      "uiComponent": "PrimaryLayout"
    }
    """
    Then the response status code should be 201
    And no Mercure publish failure should have been logged
