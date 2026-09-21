Feature: Expire a user's refresh tokens from the console
  In order to revoke a user's sessions
  As an operator
  I can expire a user's refresh tokens by username or email address

  @loginUser
  Scenario: Refresh tokens are expired for a user found by username
    Given I have a refresh token
    When I run the refresh tokens expire command for "NEW_USER"
    Then the command should have succeeded
    And the refresh token should be expired

  @loginUser
  Scenario: Refresh tokens are expired for a user found by email address
    Given I have a refresh token
    When I run the refresh tokens expire command for "user@example.com" using the field "emailAddress"
    Then the command should have succeeded
    And the refresh token should be expired

  @loginUser
  Scenario: A username that is only a user's email address finds nobody
    Given I have a refresh token
    When I run the refresh tokens expire command for "user@example.com"
    Then the command should have failed with a message containing 'User with username "user@example.com" not found.'
    And the refresh token should not be expired

  @loginUser
  Scenario: A username that is also another user's email address is reported as ambiguous
    Given I have a refresh token
    And there is a user with the username "user@example.com" password "password" and role "ROLE_USER" and the email address "other@example.com"
    When I run the refresh tokens expire command for "user@example.com"
    Then the command should have failed with a message containing "cannot be identified. No refresh-tokens were expired."
    And the refresh token should not be expired

  @loginUser
  Scenario: A field other than username or emailAddress is rejected
    Given I have a refresh token
    When I run the refresh tokens expire command for "new_user" using the field "id"
    Then the command should have failed with a message containing "Use one of: username, emailAddress."
    And the refresh token should not be expired
