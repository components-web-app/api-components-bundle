Feature: Mercure authorization cookies and messages are published
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A Mercure authorization cookie is set WITHOUT topic draft access
    When I send a "GET" request to "/docs.jsonld"
    Then the response status code should be 200
    And the response should have a "mercureAuthorization" cookie
    And the mercure cookie should not contain draft resource topics

  @loginAdmin
  Scenario: A Mercure authorization cookie is set WITH topic draft access
    When I send a "GET" request to "/docs.jsonld"
    Then the response status code should be 200
    And the response should have a "mercureAuthorization" cookie
    And the mercure cookie should contain draft resource topics
