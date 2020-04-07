Feature: Form component that defines a form type created in the application
  In order to provide a form to the front-end appliation
  As an application / client
  I need to be able to create the component and recieve serialized forms with validation and submission endpoints

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  @login
  Scenario: Create a form component resource
    When I send a "POST" request to "/component/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\Functional\\TestBundle\\Form\\TestType"
    }
    """
    Then the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a PATCH request to the form with a valid field
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": "John Smith"
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a PATCH request to the form with an invalid field
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": ""
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a PATCH request to the form with multiple valid fields
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": "John Smith",
        "company": "IT"
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a PATCH request to the form with an invalid field within multiple fields
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": "",
        "company": "IT"
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"


  @createSchema
  @createTestForm
  Scenario: I send a POST request to the form with all valid fields
    When I send a "POST" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": "John Smith",
        "company": "IT"
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a POST request to the form with an invalid field
    When I send a "POST" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {
        "name": "",
        "company": "IT"
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @createTestForm
  Scenario: I send a POST request to the form with an invalid root key
    When I send a "POST" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "invalid_root_key": {}
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be a superset of:
    """
    {
      "hydra:description": "Form object key could not be found. Expected: <b>test</b>: { \"input_name\": \"input_value\" }"
    }
    """

  @createSchema
  @createTestForm
  Scenario: I send a PATCH request to the form with no fields
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {}
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

    # NESTED FORMS NEED TESTS!!!