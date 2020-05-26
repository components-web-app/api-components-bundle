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

  Scenario Outline: I can get a Password updated form with pre-filled fields
    Given there is a "password_update" form
    When I send a "GET" request to the component "password_update_form" and the postfix "<postfix>"
    Then the response status code should be 200
    And the JSON node "formView.children[0].vars.value" should be equal to "<expectedUsername>"
    And the JSON node "formView.children[1].vars.value" should be equal to "<expectedToken>"
    Examples:
      | postfix                 | expectedUsername | expectedToken |
      | ?username=abc&token=123 | abc              | 123           |
      |                         |                  |               |

  Scenario: I can reset my password successfully
    Given there is a "password_update" form
    And there is a user with the username "username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "now"
    When I send a "POST" request to the component "password_update_form" and the postfix "/submit" with body:
    """
    {
      "password_update": {
        "username": "username",
        "newPasswordConfirmationToken": "abc123",
        "plainPassword": {
          "first": "mynewpassword",
          "second": "mynewpassword"
        }
      }
    }
    """
    Then the response status code should be 200
    And I should get a "password_changed" email sent to the email address "test.user@example.com"

  Scenario Outline: I cannot reset my password with invalid data
    Given there is a "password_update" form
    And there is a user with the username "username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "<requestedAt>"
    When I send a "POST" request to the component "password_update_form" and the postfix "/submit" with body:
    """
    {
      "password_update": {
        "username": "<username>",
        "newPasswordConfirmationToken": "<token>",
        "plainPassword": {
          "first": "mynewpassword",
          "second": "mynewpassword"
        }
      }
    }
    """
    Then the response status code should be <status>
    Examples:
      | username | token  | requestedAt         | status |
      | username | abc123 | 1970-01-01 00:00:00 | 404    |
      |          | abc123 | now                 | 404    |
      | username |        | now                 | 404    |
      |          |        | now                 | 404    |
      | invalid  |        | now                 | 404    |

  Scenario Outline: I should receive the form errors on an invalid password
    Given there is a "password_update" form
    And there is a user with the username "username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "now"
    When I send a "POST" request to the component "password_update_form" and the postfix "/submit" with body:
    """
    {
      "password_update": {
        "username": "username",
        "newPasswordConfirmationToken": "abc123",
        "plainPassword": {
          "first": "<passwordFirst>",
          "second": "<passwordSecond>"
        }
      }
    }
    """
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "form.schema.json"
    And the JSON node "formView.children[2].children[0].vars.errors[0]" should be equal to "<message>"
    Examples:
      | passwordFirst | passwordSecond | message                                            |
      | a             | a              | Your password must be more than 6 characters long. |
      | mynewpassword |                | The password fields must match.                    |
      |               | mynewpassword  | The password fields must match.                    |
