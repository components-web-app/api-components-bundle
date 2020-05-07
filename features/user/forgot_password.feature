Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  Scenario: I can request a new password
    Given there is a user with the username "my_username" password "password" and role "ROLE_USER"
    When I send a "GET" request to "/password/reset/request/my_username"
    Then the response status code should be 200
    And I should get a "password_reset" email sent to the email address "test.user@example.com"

  @wip
  Scenario: I can request a new password with a custom return URL

  @wip
  Scenario: I can reset my password with a given token
