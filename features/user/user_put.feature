Feature: A PUT to a user changes only the fields it sends
  In order to update a user without losing their credentials
  As a super admin or the user themselves
  A PUT must not reset the fields missing from its body, including those nobody can write

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginSuperAdmin
  Scenario: A super admin PUT with only a username keeps the user's other fields
    Given there is a user with the username "my_user" password "password" and role "ROLE_ADMIN" and the email address "my_user@example.com"
    When I send a "PUT" request to the resource "user" with body:
    """
    {
      "username": "renamed_user"
    }
    """
    Then the response status code should be 200
    And the user "renamed_user" should have the email address "my_user@example.com", the role "ROLE_ADMIN", the password "password", be enabled and have a verified email address

  @loginSuperAdmin
  Scenario: A super admin PUT with a new username and email address keeps the password, roles and verification
    Given there is a user with the username "my_user" password "password" and role "ROLE_ADMIN" and the email address "my_user@example.com"
    When I send a "PUT" request to the resource "user" with body:
    """
    {
      "username": "renamed_user",
      "emailAddress": "renamed@example.com"
    }
    """
    Then the response status code should be 200
    And the user "renamed_user" should have the email address "renamed@example.com", the role "ROLE_ADMIN", the password "password", be enabled and have a verified email address

  @loginUser
  Scenario: A user's own PUT with only a new email address keeps their other fields
    When I send a "PUT" request to the resource "login_user" with body:
    """
    {
      "newEmailAddress": "changed@example.com"
    }
    """
    Then the response status code should be 200
    And the user "new_user" should have the email address "user@example.com", the role "ROLE_USER", the password "password", be enabled and have a verified email address
