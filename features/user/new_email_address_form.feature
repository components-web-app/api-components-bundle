Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  @resetRefererAndOrigin
  Scenario Outline: Submit a successful change email request
    Given there is a "new_email" form
    And I add "<headerName>" header equal to "<headerValue>"
    When I send a "POST" request to the component "new_email_form" and the postfix "/submit" with body:
    """
    {
      "new_email_address": {
        "newEmailAddress": "new@example.com"
      }
    }
    """
    And the response status code should be 201
    And the JSON should be valid according to the schema file "user.schema.json"
    And the JSON node "newEmailAddress" should be equal to "new@example.com"
    And the JSON node "emailAddress" should be equal to "user@example.com"
    And the JSON node "newEmailVerificationToken" should not exist
    And I should get a change_email_verification email sent
    Examples:
      | headerName | headerValue             |
      | origin     | http://www.website.com  |
      | referer    | http://www.website.com  |

  @loginUser
  @restartBrowser # << Required otherwise the BrowserKit client will have a history and auto-populate the referer header. We are testing for non-standard browser behaviour or hacks
  Scenario Outline: Test invalid referer and missing referer and origin headers
    Given there is a "new_email" form
    And I add "<headerName>" header equal to "<headerValue>"
    When I send a "POST" request to the component "new_email_form" and the postfix "/submit" with body:
    """
    {
      "new_email_address": {
        "newEmailAddress": "new@example.com"
      }
    }
    """
    And the response status code should be 400
    And the header "referer" should not exist
    And the JSON node "hydra:description" should be equal to "<expectedMessage>"
    And I should not receive any emails
    Examples:
      | headerName | headerValue           | expectedMessage                                                                                            |
      | referer    | invalid               | Could not extract `host` while parsing the `referer` header                                                |
      | referer    | no-scheme.com:90/path | Could not extract `scheme` while parsing the `referer` header                                              |
      | referer    |                       | Could not extract `host` while parsing the `referer` header                                                |
      | origin     | invalid               | Could not extract `host` while parsing the `origin` header                                                 |
      | origin     | no-scheme.com:90/path | Could not extract `scheme` while parsing the `origin` header                                               |
      | origin     |                       | Could not extract `host` while parsing the `origin` header                                                 |
      |            |                       | To generate an absolute URL to the referrer, the request must have a `origin` or `referer` header present  |
