Feature: Prevent disabled users from logging in
  In order to prevent bad users from gaining authorized access
  As an admin
  I need to be able to disable a user and prevent them logging in

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: I can use a login form (it is a helper form so that styling and front-end functionality can remain the same)
    Given there is a "login" form
    When I send a "GET" request to the component "login_form"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario: I cannot submit a login form back to the component
    Given there is a "login" form
    When I send a "POST" request to the component "login_form" and the postfix "/submit" with body:
    """
    {
      "user_login": {}
    }
    """
    Then the response status code should be 404

  Scenario: A successful login
    Given there is a user with the username "user" password "password" and role "ROLE_USER"
    When I send a "POST" request to "/login" with body:
    """
    {
      "username": "user",
      "password": "password"
    }
    """
    Then the response status code should be 200
    And the JSON node "token" should exist
    And the JSON node "refresh_token" should exist

  Scenario: A disabled user is not able to login
    Given there is a user with the username "user" password "password" and role "ROLE_USER"
    And the user is disabled
    And I add "X-AUTH-TOKEN" header equal to "not_a_secret"
    When I send a "POST" request to "/login" with body:
    """
    {
      "username": "user",
      "password": "password"
    }
    """
    Then the response status code should be 401
    And the JSON should be equal to:
    """
    {
        "code": 401,
        "message": "Your account is currently disabled."
    }
    """
