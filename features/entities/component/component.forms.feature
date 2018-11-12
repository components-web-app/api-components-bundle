Feature: Forms
  In order to support the forms entity
  As a website user
  I will receive the correct data and http status codes with the available endpoints

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @updateDatabaseSchema
  Scenario: Create a form
    When I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestHandler"
    }
    """
    Then the response status code should be 201
    And save the entity id as form
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/components/form.json"

  Scenario: Submit valid form field
    When I send a PATCH request to the sub-resource submit of form with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200

  Scenario: Submit valid form
    When I send a POST request to the sub-resource submit of form with body:
    """
    { "test": { "name": "Valid name" } }
    """
    Then the response status code should be 200
    And the service "Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler" should have property "info" with a value of "Form submitted"

  Scenario: Do NOT create form with invalid class name
    When I send a POST request to "/forms" with body:
    """
    {
      "formType": "InvalidClassName"
    }
    """
    Then the response status code should be 400

  Scenario: Do NOT create form with invalid form success handler
    When I send a POST request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "InvalidSuccessHandler"
    }
    """
    Then the response status code should be 400

  Scenario: Submit invalid form field
    When I send a POST request to the sub-resource submit of form with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  Scenario: Submit invalid form
    When I send a POST request to the sub-resource submit of form with body:
    """
    { "test": { "name": "" } }
    """
    Then the response status code should be 400

  @dropSchema
  Scenario: Delete a form
    When I send a DELETE request to the entity form
    Then the response status code should be 204
