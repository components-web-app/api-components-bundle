Feature: Timestamps on everything a scaffold saves
  In order to load a scaffold whose entities have NOT NULL timestamps
  As a developer
  I need every timestamped entity the builder saves to get its created and modified dates, however it reaches the database

  Scenario: An entity given only to persist() and a component held only in a page data field are timestamped
    When I run the command "doctrine:fixtures:load --append --group=cwa_timestamped"
    Then there should be 1 DummyTimestamped with a created and a modified date
    And there should be 1 DummyTimestampedComponent with a created and a modified date
