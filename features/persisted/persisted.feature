Feature: A user should know whether the data is a persisted object in the database
  In order to know whether the object can be modified
  As an API user
  I must be able to know whether the object is persisted in the database or dynamically added to the output

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: An object has been persisted to the database
    Given there is a DummyComponent
    When I send a "GET" request to the component "dummy_component"
    Then the response status code should be 200
    And the JSON node __PERSISTED__ should contain "true"

  Scenario: An object has not been persisted to the database
    When I send a "GET" request to "/_/dummy_unpersisted_components/123"
    Then the response status code should be 200
    And the JSON node __PERSISTED__ should contain "false"
