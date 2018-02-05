Feature: Forms
  In order to support the forms entity
  As a website user
  I will receive the correct data and http status codes with the available endpoints

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Create a form
    When I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestHandler"
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "formType": { "type": "string" },
        "successHandler": { "type": "string" },
        "form": { "type": "null" }
      }
    }
    """

  Scenario: Get a form
    When I send a "GET" request to "/forms/1"
    Then the response status code should be 200
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "formType": { "type": "string" },
        "successHandler": { "type": "string" },
        "form": {
          "type": "object",
          "properties": {
            "vars": { "type": "object" },
            "children": { "type": "array" }
          }
        }
      }
    }
    """

  Scenario: Submit valid form field
    When I send a "PATCH" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200

  Scenario: Submit valid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200
    And the service "Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler" should have property "info" with a value of "Form submitted"

  Scenario: Do NOT create form with invalid class name
    When I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "InvalidClassName"
    }
    """
    Then the response status code should be 400

  Scenario: Do NOT create form with invalid form success handler
    When I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "InvalidSuccessHandler"
    }
    """
    Then the response status code should be 400

  Scenario: Submit invalid form field
    When I send a "PATCH" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  Scenario: Submit invalid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  @dropSchema
  Scenario: Delete a form
    When I send a "DELETE" request to "/forms/1"
    Then the response status code should be 204
