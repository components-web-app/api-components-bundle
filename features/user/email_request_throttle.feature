Feature: Requests for user emails are throttled per flow
  In order to stop a user being flooded with emails while still being able to get one that did not arrive
  As an application / client
  I must be told when a request is throttled and for how long, and each email flow must have its own throttle

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  Scenario: A password reset request inside its throttle is too many requests and changes nothing
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "-1 hour"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 429
    And the response header "Retry-After" should be a number of seconds between 82700 and 82800
    And the header "Cache-Control" should contain "private"
    And I should not receive any emails
    And the password reset token for the user "my_username" should still be "abc123"

  Scenario: A password reset request after its throttle sends the email
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    And the user has the newPasswordConfirmationToken "abc123" requested at "-25 hours"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And I should get a "password_reset" email sent to the email address "test.user@example.com"

  Scenario: A resend of the email verification inside its throttle is too many requests and changes nothing
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user email is not verified with the token "abc123"
    And the user email verification was requested at "-2 minutes"
    When I send a "GET" request to "/resend-verify-email/my_username"
    Then the response status code should be 429
    And the response header "Retry-After" should be a number of seconds between 170 and 180
    And the header "Cache-Control" should contain "private"
    And I should not receive any emails
    And the email verification token for the user "my_username" should still be "abc123"

  Scenario: A resend of the email verification after its throttle sends the email
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user email is not verified with the token "abc123"
    And the user email verification was requested at "-6 minutes"
    When I send a "GET" request to "/resend-verify-email/my_username"
    Then the response status code should be 200
    And I should get a "verify_email" email sent to the email address "user@example.com"

  Scenario: A resend of the new email address confirmation inside its throttle is too many requests and changes nothing
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has a new email address "new@example.com" and confirmation token "abc123" and the email was sent at "-2 minutes"
    When I send a "GET" request to "/resend-verify-new-email/my_username"
    Then the response status code should be 429
    And the response header "Retry-After" should be a number of seconds between 170 and 180
    And the header "Cache-Control" should contain "private"
    And I should not receive any emails
    And the new email confirmation token for the user "my_username" should still be "abc123"

  Scenario: A resend of the new email address confirmation after its throttle sends the email
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has a new email address "new@example.com" and confirmation token "abc123" and the email was sent at "-6 minutes"
    When I send a "GET" request to "/resend-verify-new-email/my_username"
    Then the response status code should be 200
    And I should get a "change_email_confirmation" email sent to the email address "user@example.com"

  Scenario: The new email address confirmation is not throttled by a recent password reset request
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has the newPasswordConfirmationToken "reset123" requested at "-10 minutes"
    And the user has a new email address "new@example.com" and confirmation token "abc123" and the email was sent at "-10 minutes"
    When I send a "GET" request to "/resend-verify-new-email/my_username"
    Then the response status code should be 200
    And I should get a "change_email_confirmation" email sent to the email address "user@example.com"

  Scenario: The email verification is not throttled by a recent password reset request
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has the newPasswordConfirmationToken "reset123" requested at "-10 minutes"
    And the user email is not verified with the token "abc123"
    And the user email verification was requested at "-10 minutes"
    When I send a "GET" request to "/resend-verify-email/my_username"
    Then the response status code should be 200
    And I should get a "verify_email" email sent to the email address "user@example.com"

  Scenario: A password reset request is not throttled by a recent new email address confirmation
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER" and the email address "user@example.com"
    And the user has a new email address "new@example.com" and confirmation token "abc123" and the email was sent at "-1 minute"
    And the user email verification was requested at "-1 minute"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And I should get a "password_reset" email sent to the email address "user@example.com"

  Scenario Outline: An unknown user is still not found
    When I send a "GET" request to "<path>"
    Then the response status code should be 404
    And I should not receive any emails
    Examples:
      | path                              |
      | /password/reset/request/no_user   |
      | /resend-verify-email/no_user      |
      | /resend-verify-new-email/no_user  |
