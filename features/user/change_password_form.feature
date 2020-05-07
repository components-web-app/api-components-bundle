Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  @loginUser
  Scenario: I can change my password
    Given there is a "change_password" form
    When I send a "POST" request to the component "change_password_form" and the postfix "/submit" with body:
    """
    {
      "change_password": {
        "username": "new@example.com",
        "oldPassword": "new@example.com",
        "plainPassword": {
          "first": "new_password",
          "second": "new_password"
        }
      }
    }
    """
    Then the response status code should be 201
    And I should get a change_password_notification email sent
    And the password should be "new_password" for username "user@example.com"
    And the JSON should be valid according to the schema file "user.schema.json"

  @loginUser
  @wip
  Scenario: I cannot change my password if my old password is incorrect
    Given there is a "change_password" form

  @loginUser
  @wip
  Scenario: I cannot change my password if my passwords do not match
    Given there is a "change_password" form

  @loginUser
  @wip
  Scenario: As a user, I cannot change another user's password as a user
    Given there is a "change_password" form

  @loginAdmin
  @wip
  Scenario: As an admin, I cannot change another user's password
    Given there is a "change_password" form

  @loginSuperAdmin
  @wip
  Scenario: As a Super Admin I can change another user's password
    Given there is a "change_password" form
