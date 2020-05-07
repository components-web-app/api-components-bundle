Feature: Prevent disabled users from logging in
  In order to prevent bad users from gaining authorized access
  As an admin
  I need to be able to disable a user and prevent them logging in

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @wip
  Scenario: A disabled user is not able to login
