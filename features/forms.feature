Feature: Forms
  In order to support submitting a form back to the API
  As a website user
  I can create, validate and submit forms

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
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

  Scenario: Create a form
    When I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestHandler"
    }
    """
    Then the response status code should be 201

  Scenario: Submit invalid form field
    When I send a "PATCH" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  Scenario: Submit valid form field
    When I send a "PATCH" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200

  Scenario: Submit invalid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  @dropSchema
  Scenario: Submit valid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200
    And the service "Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler" should have property "info" with a value of "Form submitted"
