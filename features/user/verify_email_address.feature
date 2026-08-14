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

  @loginSuperAdmin
  Scenario: Email becomes unverified when the email address is changed by the API
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "referer" header equal to "http://www.website.com"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "emailAddress": "new@email.com"
    }
    """
    Then the response status code should be 200
    And I should get a "verify_email" email sent to the email address "new@email.com"

  Scenario: I can verify my email address
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user email is not verified with the token "abc123"
    When I send a "GET" request to "/verify-email/my_username/abc123"
    Then the response status code should be 200
    And the user "my_username" should have a verified email address

  # ResendVerifyEmailAddressAction generates a fresh token, so its route takes a username and no
  # token — matching its sibling /resend-verify-new-email/{username}. It was registered at
  # /verify-email/{username}/{token}, a duplicate of the route above, and Symfony resolves a
  # duplicate path to the first match: the action was unreachable and the path the module calls was
  # registered nowhere. Nothing failed, because no scenario had ever hit this route.
  Scenario: I can request a new email address verification email
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user email is not verified
    And I add "referer" header equal to "http://www.website.com"
    When I send a "GET" request to "/resend-verify-email/my_username"
    Then the response status code should be 200
    And I should get a "verify_email" email sent to the email address "user@example.com"

  # UserDataProcessor::findUserByUsername throws rather than returning null, so without a catch an
  # unknown username is an uncaught exception — a 500 leaking "Username not found". 404 matches
  # PasswordRequestAction, the only one of these actions that already handled it.
  Scenario: Requesting a verification email for an unknown username is a 404, not an error
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user email is not verified
    And I add "referer" header equal to "http://www.website.com"
    When I send a "GET" request to "/resend-verify-email/no_user"
    Then the response status code should be 404
    And I should not receive any emails

  Scenario Outline: I cannot verify email addresses with incorrect parameters
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user email is not verified with the token "abc123"
    When I send a "GET" request to "/verify-email/<username>/<token>"
    Then the response status code should be 404
    And the user "my_username" should have an unverified email address
    Examples:
      | username    | token  |
      | my_username | wrong  |
      | wrong       | abc123 |
