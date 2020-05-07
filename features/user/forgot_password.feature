Feature: Forgot password system
  In order to reset a password if I have forgotten it
  As an API user
  I must be able to have a process to reset it

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  Scenario: I can request a new password
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And I should get a "password_reset" email sent to the email address "test.user@example.com"

  Scenario: I get the appropriate status code if the user is not found
    When I send a "GET" request to "/password/reset/request/no_user"
    Then the response status code should be 404

  Scenario: I can request a new password with a custom return URL
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    When I send a "GET" request to "/password/reset/request/my_username?password_redirect=/another-path/{{ username }}/{{ token }}"
    Then the response status code should be 200
    And I should get a "custom_password_reset" email sent to the email address "test.user@example.com"

  Scenario Outline: I can reset my password with a given token
    Given there is a user with the username "username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "<requestedAt>"
    When I send a "POST" request to "/password/update" with body:
    """
    {
      "username": "<username>",
      "token": "<token>",
      "password": "mynewpassword"
    }
    """
    Then the response status code should be <status>
    Examples:
      | username  | token       | requestedAt         | status  |
      | username  | abc123      | now                 | 200     |
      | username  | abc123      | 1970-01-01 00:00:00 | 404     |
      |           | abc123      | now                 | 404     |
      | username  |             | now                 | 404     |
      |           |             | now                 | 404     |
      | invalid   |             | now                 | 404     |
    
  Scenario: The password validation should apply and API Platform should take over the response as constraint violation list
    Given there is a user with the username "username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "now"
    When I send a "POST" request to "/password/update" with body:
    """
    {
      "username": "username",
      "token": "abc123",
      "password": "a"
    }
    """
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"
    And the JSON node "violations[0].message" should be equal to "Your password must be more than 6 characters long."
