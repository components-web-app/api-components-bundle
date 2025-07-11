Feature: Receive notification emails on important user changes
  In order to keep control of my account
  As a user
  I should receive notification emails so I can take action

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  @loginSuperAdmin
  Scenario: I receive an email when my user has been enabled
    Given there is a user with the username "user@user.co" password "password" and role "ROLE_USER"
    And the user is disabled
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "enabled": true
    }
    """
    Then the response status code should be 200
    And I should get a "enabled_notification" email sent to the email address "test.user@example.com"

  @loginSuperAdmin
  Scenario: I do not receive an email if my account is disabled
    Given there is a user with the username "user@user.co" password "password" and role "ROLE_USER"
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "enabled": false
    }
    """
    Then the response status code should be 200
    And I should not receive any emails

  @loginSuperAdmin
  Scenario: I do not receive an email is the account was already enabled
    Given there is a user with the username "user@user.co" password "password" and role "ROLE_USER"
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "enabled": true
    }
    """
    Then the response status code should be 200
    And I should not receive any emails

  @loginSuperAdmin
  Scenario: I receive an email when my username has been changed
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    And I add "referer" header equal to "http://www.website.com"
    And there is a user with the username "my_username" password "pass" and role "ROLE_USER"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "username": "test.user@example.com"
    }
    """
    Then the response status code should be 200
    And I should get a "username_changed_notification" email sent to the email address "test.user@example.com"

