Feature: Form component that defines a form type created in the application
  In order to provide a form to the front-end application
  As an application / client
  I need to be able to create the component and receive serialized forms with validation and submission endpoints

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  @loginAdmin
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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
  @create_test_form
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

  @createSchema
  @create_nested_form
  Scenario: I can send a valid field for validation of one of the children in a CollectionType
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [
          {},
          {
            "name": "John Smith"
          }
        ]
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: I can send null children in place of an empty object and validation will still pass only for the submitted fields
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [
          null,
          {
            "name": "John Smith"
          }
        ]
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: I can send an invalid field for validation of one of the children in a CollectionType
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [
          {},
          {
            "name": ""
          }
        ]
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: I can validate a valid field that is a collection type with a simple field
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "text_children": [
          "hello"
        ]
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: I can validate a valid field that is a collection type with multiple simple field
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "text_children": [
          "hello",
          "another"
        ]
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: I can validate an invalid field that is a collection type with a simple field
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "text_children": [
          "1"
        ]
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: Each text_children should have a minimum length of 1 - post invalid form
    When I send a "POST" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [
          {
            "name": "A name"
          }
        ],
        "text_children": [
          "1"
        ]
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: Children is required - post an invalid form
    When I send a "POST" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [],
        "text_children": [
          "with minimum length"
        ]
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @create_nested_form
  Scenario: Post a valid form
    When I send a "POST" request to the component "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "children": [
          {
            "name": "A name"
          }
        ],
        "text_children": [
          "with minimum length"
        ]
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"

  @createSchema
  @create_test_repeated_form
  Scenario: Validate repeated field - valid
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_repeated_form" and the postfix "/submit" with body:
    """
    {
      "test_repeated": {
        "repeat": {
          "first": "something",
          "second": "something"
        }
      }
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  @createSchema
  @create_test_repeated_form
  Scenario: Validate repeated field - invalid
    Given I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the component "test_repeated_form" and the postfix "/submit" with body:
    """
    {
      "test_repeated": {
        "repeat": {
          "first": "something",
          "second": "no_same"
        }
      }
    }
    """
    Then the response status code should be 400
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"
