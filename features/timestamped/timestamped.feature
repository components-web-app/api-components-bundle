Feature: Timestamped resources
  In order to record resource created and modified date/times
  As an application developer
  I must be able to configure a resource which is timestamped

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I should be able to create a new timestamped resource
    When I send a "POST" request to "/dummy_timestampeds" with body:
    """
    {}
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "timestamped.schema.json"

  @loginUser
  Scenario: I should not be able to write to the timestamped fields
    When I send a "POST" request to "/dummy_timestampeds" with body:
    """
    {
      "createdAt": "1970-01-01 00:00:00",
      "modifiedAt": "1970-01-01 00:00:00"
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "timestamped.schema.json"
    And the JSON node "createdAt" should be now
    And the JSON node "modifiedAt" should be now

  @loginUser
  Scenario: Use custom timestamped fields
    Given there is a DummyCustomTimestamped resource
    When I send a "GET" request to the component "dummy_custom_timestamped"
    Then the response status code should be 200
    And the JSON node "customCreatedAt" should be now
    And the JSON node "customModifiedAt" should be now

  @loginUser
  Scenario: Use custom timestamped fields
    Given there is a DummyTimestampedWithSerializationGroups resource
    When I send a "GET" request to the component "dummy_custom_timestamped"
    Then the response status code should be 200
    And the JSON node "createdAt" should be now
    And the JSON node "modifiedAt" should be now
