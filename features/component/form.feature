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
    And the response status code should be 201
    And the response should be in JSON
    And the header "Content-Type" should be equal to "application/ld+json; charset=utf-8"
    And the JSON should be valid according to the schema file "form.schema.json"
