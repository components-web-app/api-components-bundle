Feature: Add a /me endpoint
  In order to fetch the current logged in user
  As an API user
  I must be able to have a deterministic endpoint

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can retrieve the current logged in user object
    When I send a "GET" request to "/me"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "user.schema.json"
    And the JSON node "_metadata.mercureSubscribeTopics[0]" should be equal to "http://example.com:80/_/component_groups/{id}{._format}"

  Scenario: I can retrieve the current logged in user object
    When I send a "GET" request to "/me"
    Then the response status code should be 401
