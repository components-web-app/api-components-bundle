Feature: Links in user emails only point at an allowed origin
  In order that a token sent by email can only be followed back to this application
  As an application
  I must build the scheme, host and port of every email link from configuration, never from an unchecked request header

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @restartBrowser
  Scenario: An Origin header that is not allowed is refused and no email is sent
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "https://evil.example"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: A Referer header that is not allowed is refused and no email is sent
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Referer" header equal to "https://evil.example/anything?x=1"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: An Origin header that is not allowed is refused even when the Referer is allowed
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Referer" header equal to "http://www.website.com"
    And I add "Origin" header equal to "https://evil.example:8443"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: The port is part of the origin, so an allowed host on another port is refused
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "http://www.website.com:8080"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: An allowed Origin with a port is used with its port
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "https://app.website.com:8443"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And the link in the sent email should start with "https://app.website.com:8443/reset-password/my_username/"

  @restartBrowser
  Scenario: An allowed Origin is used
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "http://www.website.com"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And the link in the sent email should start with "http://www.website.com/reset-password/my_username/"

  @restartBrowser
  Scenario: An allowed origin is matched against the whole origin, not a substring of it
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "http://www.website.com.evil.example"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario Outline: A redirect path query value that is not a plain relative path falls back to the configured path
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Referer" header equal to "http://www.website.com"
    When I send a "GET" request to "/password/reset/request/my_username?password_redirect=<redirect>"
    Then the response status code should be 200
    And the link in the sent email should start with "http://www.website.com/reset-password/my_username/"
    Examples:
      | redirect                                              |
      | https://evil.example/steal/{{ username }}/{{ token }} |
      | //evil.example/{{ token }}                            |
      | /%5Cevil.example/{{ token }}                          |
      | %5C%5Cevil.example/{{ token }}                        |
      | /%09/evil.example/{{ token }}                         |
      | /path//evil.example/{{ token }}                       |
      | javascript:alert(1)                                   |
      | relative/{{ token }}                                  |

  @restartBrowser
  Scenario: A relative redirect path query value is still used
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Referer" header equal to "http://www.website.com"
    When I send a "GET" request to "/password/reset/request/my_username?password_redirect=/another-path/{{ username }}/{{ token }}"
    Then the response status code should be 200
    And the link in the sent email should start with "http://www.website.com/another-path/my_username/"

  @restartBrowser
  Scenario: A resent email verification with an Origin header that is not allowed is refused
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user email is not verified with the token "abc"
    And I add "Origin" header equal to "https://evil.example"
    When I send a "GET" request to "/resend-verify-email/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: A resent new email address confirmation with an Origin header that is not allowed is refused
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has a new email address "new@example.com" and confirmation token "abc123" and the email was sent at "-1 day"
    And I add "Origin" header equal to "https://evil.example"
    When I send a "GET" request to "/resend-verify-new-email/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: A registration with an Origin header that is not allowed sends no welcome email
    Given there is a "register" form
    And I add "Origin" header equal to "https://evil.example"
    When I send a "POST" request to the resource "register_form" and the postfix "/submit" with body:
    """
    {
      "user_register": {
        "username": "new_user",
        "emailAddress": "user@example.com",
        "plainPassword": {
          "first": "password",
          "second": "password"
        }
      }
    }
    """
    Then the response status code should be 400
    And I should not receive any emails

  @restartBrowser
  Scenario: With no Origin or Referer header the configured default origin is used
    Given links in user emails default to the origin "https://default.website.com"
    And there is a user with the username "my_username" password "password" and role "ROLE_USER"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And the link in the sent email should start with "https://default.website.com/reset-password/my_username/"

  @restartBrowser
  Scenario: An Origin header that is not allowed falls back to the configured default origin
    Given links in user emails default to the origin "https://default.website.com"
    And there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "https://evil.example"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And the link in the sent email should start with "https://default.website.com/reset-password/my_username/"

  @restartBrowser
  Scenario: An allowed Origin header is preferred to the configured default origin
    Given links in user emails default to the origin "https://default.website.com"
    And there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And I add "Origin" header equal to "http://www.website.com"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And the link in the sent email should start with "http://www.website.com/reset-password/my_username/"

  @restartBrowser
  Scenario: With no Origin or Referer header and no default origin the request is refused
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 400
    And I should not receive any emails

  @loginSuperAdmin
  @restartBrowser
  Scenario: The login link in the account enabled email uses the default origin when the Origin header is not allowed
    Given links in user emails default to the origin "https://default.website.com"
    And there is a user with the username "user@user.co" password "password" and role "ROLE_USER"
    And the user is disabled
    And I add "Content-Type" header equal to "application/merge-patch+json"
    And I add "Origin" header equal to "https://evil.example"
    When I send a "PATCH" request to the resource "user" with body:
    """
    {
      "enabled": true
    }
    """
    Then the response status code should be 200
    And the link in the sent email should start with "https://default.website.com/login"
