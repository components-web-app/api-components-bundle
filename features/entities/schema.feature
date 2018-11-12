Feature: Database schema
  In order for the entities to work properly
  The application should have a valid schema

  @updateDatabaseSchema
  Scenario: Create the schema
    Then the database schema should be valid
    And the table layout should exist

  Scenario: Drop the schema
    When drop the schema
    Then there should be 0 tables in the database
