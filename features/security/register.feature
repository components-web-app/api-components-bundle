Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @createRegisterForm
  Scenario: Submit a user registration form
    Given a user exists with the username "user" password "password" and role "ROLE_USER"
    When I send a "POST" request to the component "register_form" and the postfix "/submit" with body:
    """
    {
      "user_register": {
        "username": "user@email.com",
        "plainPassword": {
          "first": "password",
          "second": "password"
        }
      }
    }
    """
    And the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be a superset of:
    """
    {
        "@context": "/contexts/User",
        "@type": "User",
        "username": "user@email.com",
        "emailAddress": "user@email.com",
        "roles": [
            "ROLE_USER"
        ],
        "enabled": true,
        "newEmailAddress": "user@email.com",
        "__PERSISTED__": true
    }
    """
    And the JSON should be valid according to the schema file "user.schema.json"
