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
        "oldPassword": "password",
        "plainPassword": {
          "first": "new_password",
          "second": "new_password"
        }
      }
    }
    """
    And the response status code should be 201
    And I should get a "change_password_notification" email sent
    And the password should be "new_password" for username "user@example.com"
    And the JSON should be valid according to the schema file "user.schema.json"

  @loginUser
  Scenario: I cannot change my password if my old password is incorrect
    Given there is a "change_password" form
    When I send a "POST" request to the component "change_password_form" and the postfix "/submit" with body:
    """
    {
      "change_password": {
        "oldPassword": "invalid",
        "plainPassword": {
          "first": "new_password",
          "second": "new_password"
        }
      }
    }
    """
    Then the response status code should be 400
    And I should not receive any emails
    And the JSON should be valid according to the schema file "form.schema.json"
    And the JSON node "formView.children[1].vars.errors[0]" should be equal to "You have not entered your current password correctly. Please try again."
    And the password should be "password" for username "user@example.com"

  @loginUser
  Scenario: I cannot change my password if my passwords do not match
    Given there is a "change_password" form
    When I send a "POST" request to the component "change_password_form" and the postfix "/submit" with body:
    """
    {
      "change_password": {
        "oldPassword": "password",
        "plainPassword": {
          "first": "new_password",
          "second": "new_password_no_match"
        }
      }
    }
    """
    Then the response status code should be 400
    And I should not receive any emails
    And the JSON should be valid according to the schema file "form.schema.json"
    And the JSON node "formView.children[2].children[0].vars.errors[0]" should be equal to "The passwords you entered are not the same."
    And the password should be "password" for username "user@example.com"

  @loginUser
  Scenario: If I submit the username field, it is ignored.
    Given there is a "change_password" form
    And there is a user with the username "another_user" password "password" and role "ROLE_USER"
    When I send a "POST" request to the component "change_password_form" and the postfix "/submit" with body:
    """
    {
      "change_password": {
        "username": "another_user",
        "oldPassword": "password",
        "plainPassword": {
          "first": "new_password",
          "second": "new_password"
        }
      }
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "user.schema.json"
    And the JSON node "username" should be equal to "user@example.com"
    And the password should be "new_password" for username "user@example.com"
