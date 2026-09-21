Feature: Form component that defines a form type created in the application
  In order to provide a form to the front-end application
  As an application / client
  I need to be able to create the component and receive serialized forms with validation and submission endpoints

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: Create a form component and receive serialized form views
    When I send a "POST" request to "/component/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Form\\TestType"
    }
    """
    Then the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario Outline: I can validate a single form field
    Given there is a "test" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_form" and the postfix "/submit" with body:
     """
     {
       "test": {
         "name": "<name>"
       }
     }
     """
    Then the response status code should be <status>
    And the response should be in JSON
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"
    Examples:
      | name       | status |
      | John Smith | 200    |
      |            | 422    |


  Scenario: The @id in the submit response is the canonical form IRI, not the /submit endpoint
    Given there is a "test" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {"test": {"name": "John Smith"}}
    """
    Then the response status code should be 200
    And the JSON node "@id" should be equal to the IRI of the resource "test_form"

  Scenario: The @id in the POST submit response is the canonical form IRI, not the /submit endpoint
    Given there is a "test" form
    When I send a "POST" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {"test": {"name": "John Smith", "company": "Silverback"}}
    """
    Then the response status code should be 201
    And the JSON node "@id" should be equal to the IRI of the resource "test_form"

  Scenario: A POST submit validates a required field the request omits
    Given there is a "test" form
    When I send a "POST" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {"test": {"name": "John Smith"}}
    """
    Then the response status code should be 422
    And the JSON node "formView.vars.valid" should be false
    And the JSON node "formView.children[0].vars.errors" should have 0 elements
    And the JSON node "formView.children[1].vars.errors[0]" should be equal to "Please provide your company"

  Scenario: A PATCH submit validates only the fields it sends and never succeeds
    Given there is a "test" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {"test": {"name": "John Smith"}}
    """
    Then the response status code should be 200
    And the JSON node "@type" should be equal to "Form"
    And the JSON node "formView.vars.valid" should be true
    And the JSON node "formView.children[1].vars.errors" should have 0 elements

  # PATCH

  Scenario Outline: I send a PATCH request to the form with multiple fields
    Given there is a "test" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_form" and the postfix "/submit" with body:
     """
     {
       "test": {
         "name": "<name>",
         "company": "<company>"
       }
     }
     """
    Then the response status code should be <status>
    And the response should be in JSON
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"
    Examples:
      | name       | company | status |
      | John Smith | company | 200    |
      |            | company | 422    |
      |            |         | 422    |

  Scenario: I send a PATCH request to the form with no fields
    Given there is a "test" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {
      "test": {}
    }
    """
    Then the response status code should be 200
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be valid according to the schema file "form.schema.json"

  # POST

  Scenario Outline: I send a POST request to the form with fields
    Given there is a "test" form
    When I send a "POST" request to the resource "test_form" and the postfix "/submit" with body:
     """
     {
       "test": {
         "name": "<name>",
         "company": "<company>"
       }
     }
     """
    Then the response status code should be <status>
    And the response should be in JSON
    And the JSON should be valid according to the schema file "form.schema.json"
    Examples:
      | name       | company | status |
      | John Smith | company | 201    |
      |            | company | 422    |
      |            |         | 422    |

  Scenario: I send a POST request to the form with an invalid root key
    Given there is a "test" form
    When I send a "POST" request to the resource "test_form" and the postfix "/submit" with body:
    """
    {
      "invalid_root_key": {}
    }
    """
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/problem+json"
    And the JSON should be a superset of:
    """
    {
      "description": "Form object key could not be found. Expected: <b>test</b>: { \"input_name\": \"input_value\" }"
    }
    """

  Scenario: CollectionType fields expose allow_add, allow_delete and prototype in the form view
    Given there is a "nested" form
    When I send a "GET" request to the resource "nested_form"
    Then the response status code should be 200
    And the JSON node "formView.children[0].vars.allow_add" should be true
    And the JSON node "formView.children[0].vars.allow_delete" should be true
    And the JSON node "formView.children[0].prototype" should exist
    And the JSON node "formView.children[1].vars.allow_add" should be true
    And the JSON node "formView.children[1].vars.allow_delete" should be true
    And the JSON node "formView.children[1].prototype" should exist

  # PATCH NESTED

  Scenario: I can send a valid field for validation of one of the children in a CollectionType
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
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
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  Scenario: I can send null children in place of an empty object and validation will still pass only for the submitted fields
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
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
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  # PATCH COLLECTION TYPE SUPPORT

  Scenario: I can send an invalid field for validation of one of the children in a CollectionType
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
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
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  Scenario: I can validate a valid field that is a collection type with a simple field
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
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
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  Scenario: I can validate a valid field that is a collection type with multiple simple field
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
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
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  Scenario: I can validate an invalid field that is a collection type with a simple field
    Given there is a "nested" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "nested_form" and the postfix "/submit" with body:
    """
    {
      "nested": {
        "text_children": [
          "1"
        ]
      }
    }
    """
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  # PATCH REPEATED FIELD TYPE

  Scenario: Validate repeated field - valid
    Given there is a "test_repeated" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_repeated_form" and the postfix "/submit" with body:
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
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  Scenario: Validate repeated field - invalid
    Given there is a "test_repeated" form
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "test_repeated_form" and the postfix "/submit" with body:
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
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be an array with each entry valid according to the schema file "form.schema.json"

  # POST minimum collection length/required validation

  Scenario: Each text_children should have a minimum length of 1 - post invalid form
    Given there is a "nested" form
    When I send a "POST" request to the resource "nested_form" and the postfix "/submit" with body:
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
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario: Children is required - post an invalid form
    Given there is a "nested" form
    When I send a "POST" request to the resource "nested_form" and the postfix "/submit" with body:
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
    Then the response status code should be 422
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be valid according to the schema file "form.schema.json"

  Scenario: Post a valid form
    Given there is a "nested" form
    When I send a "POST" request to the resource "nested_form" and the postfix "/submit" with body:
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
    Then the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should contain "application/ld+json"
    And the JSON should be valid according to the schema file "form.schema.json"
