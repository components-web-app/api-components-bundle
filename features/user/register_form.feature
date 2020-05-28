Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "referer" header equal to "http://www.website.com"

  Scenario: Submit a user registration form
    Given there is a "register" form
    And I add "referer" header equal to "http://www.website.com"
    When I send a "POST" request to the resource "register_form" and the postfix "/submit" with body:
    """
    {
      "user_register": {
        "username": "user@example.com",
        "plainPassword": {
          "first": "password",
          "second": "password"
        }
      }
    }
    """
    And the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be a superset of:
    """
    {
        "@context": "/contexts/User",
        "@type": "User",
        "username": "user@example.com",
        "emailAddress": "user@example.com",
        "_metadata": {
          "persisted": true
        }
    }
    """
    And the JSON should be valid according to the schema file "user.schema.json"
    And I should get a "user_welcome" email sent

  Scenario: Submit a duplicate user registration form
    Given there is a "register" form
    And there is a user with the username "user@email.com" password "password" and role "ROLE_USER"
    When I send a "POST" request to the resource "register_form" and the postfix "/submit" with body:
    """
    {
      "user_register": {
        "username": "user@email.com",
        "plainPassword": {
          "first": "pw",
          "second": "pw"
        }
      }
    }
    """
    And the response status code should be 400
    And the response should be in JSON
    And the JSON node "formView.children[0].vars.errors[0]" should be equal to "Sorry, that user already exists in the database."
    And the JSON node "formView.children[1].children[0].vars.errors[0]" should be equal to "Your password must be more than 6 characters long."
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario: Submit an invalid user registration form
    Given there is a "register" form
    And there is a user with the username "user" password "password" and role "ROLE_USER"
    When I send a "POST" request to the resource "register_form" and the postfix "/submit" with body:
    """
    {
      "user_register": {
        "username": ""
      }
    }
    """
    And the response status code should be 400
    And the response should be in JSON
    And the JSON node "formView.children[0].vars.errors[0]" should be equal to "Please enter a username."
    And the JSON node "formView.children[1].children[0].vars.errors[0]" should be equal to "Please enter your desired password."
    And the JSON should be valid according to the schema file "form.schema.json"
