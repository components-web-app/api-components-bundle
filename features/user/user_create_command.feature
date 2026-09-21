Feature: Create users from the console
  In order to seed and administer users safely
  As a developer
  The user create command must refuse users that fail validation

  Scenario: A valid new user is created
    When I run the user create command with the username "console_user" email "console@example.com" and password "password"
    Then the user create command should succeed
    And the user create command output should contain "Created user: console_user"
    And there should be 1 user with the username "console_user"

  Scenario: A duplicate username fails, prints the violation and writes no second user
    Given there is a user with the username "console_user" password "password" and role "ROLE_USER" and the email address "console@example.com"
    When I run the user create command with the username "console_user" email "other@example.com" and password "password"
    Then the user create command should fail
    And the user create command output should contain "Sorry, that user already exists in the database."
    And there should be 1 user with the username "console_user"

  Scenario: An invalid email address fails and writes no user
    When I run the user create command with the username "console_user" email "not-an-email" and password "password"
    Then the user create command should fail
    And the user create command output should contain "emailAddress"
    And there should be 0 users with the username "console_user"

  Scenario: Overwriting an existing user still succeeds
    Given there is a user with the username "console_user" password "password" and role "ROLE_USER" and the email address "console@example.com"
    When I run the user create command with the username "console_user" email "changed@example.com" and password "password" with overwrite
    Then the user create command should succeed
    And there should be 1 user with the username "console_user"
    And the user "console_user" should have the email address "changed@example.com"
