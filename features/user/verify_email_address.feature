Feature: Email address verification
  In order to ensure only real users can login
  As an application developer
  I should be able to get users to verify their email address and prevent logins

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A use with an unverified email is not able to login
    Given there is a user with the username "user" password "password" and role "ROLE_USER"
    And the user email is not verified
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
        "message": "Please verify your email address before logging in. If you did not receive a confirmation email please try resetting your password using the forgot password feature."
    }
    """