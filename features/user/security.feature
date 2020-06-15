Feature: Prevent disabled users from logging in
  In order to prevent bad users from gaining authorized access
  As an admin
  I need to be able to disable a user and prevent them logging in

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: I can use a login form (it is a helper form so that styling and front-end functionality can remain the same)
    Given there is a "login" form
    When I send a "GET" request to the resource "login_form"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario: I cannot submit a login form back to the resource
    Given there is a "login" form
    When I send a "POST" request to the resource "login_form" and the postfix "/submit" with body:
    """
    {
      "user_login": {}
    }
    """
    Then the response status code should be 404

  Scenario: A disabled user is not able to login
    Given there is a user with the username "user" password "password" and role "ROLE_USER"
    And the user is disabled
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

  Scenario: A successful login
    Given there is a user with the username "user" password "password" and role "ROLE_USER"
    When I send a "POST" request to "/login" with body:
    """
    {
      "username": "user",
      "password": "password"
    }
    """
    Then the response status code should be 204
    And the response should be empty
    And the response should have a "api_component" cookie
    And the header "set-cookie" should contain "secure; httponly; samesite=lax"
    And 1 refresh token should exist

  @loginUser
  Scenario: Expired JWT tokens should be refreshed
    Given my JWT token has expired
    When I send a "GET" request to "/me"
    Then the response status code should be 200
    And the refresh token should be expired
    And the response should have a "api_component" cookie
    And the header "set-cookie" should contain "secure; httponly; samesite=lax"
    And 3 refresh tokens should exist

  @loginUser
  Scenario: given I have an expired refresh-token when I log in with an expired access-token, I should get a 401
    Given I have a refresh token which expires at "-1 second"
    And my JWT token has expired
    When I send a "GET" request to "/me"
    Then the response status code should be 401

  @loginUser
  Scenario: given I have a valid refresh-token and I am authenticated when I log out, all my refresh-tokens should expire
    Given I have a refresh token
    When I send a "GET" request to "/logout"
    Then the response status code should be 200
    And 1 refresh tokens should exist
    And all the refresh tokens should be expired
    And the response should have a "api_component" cookie
    And the header "set-cookie" should contain "api_component=."
    And the header "set-cookie" should contain "Max-Age=0"
