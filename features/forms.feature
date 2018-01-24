Feature: Forms
  In order to support submitting a form back to the API
  As a website user
  I am able to perform CRUD operations form form entities + submit forms

  @createSchema
  Scenario: Do NOT create form with invalid class name
    When I add "Content-Type" header equal to "application/ld+json"
    And I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "InvalidClassName",
    }
    """
    Then the response status code should be 400

  Scenario: Do NOT create form with invalid form success handler
    When I add "Content-Type" header equal to "application/ld+json"
    And I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "InvalidSuccessHandler"
    }
    """
    Then the response status code should be 400

  Scenario: Create a form
    When I add "Content-Type" header equal to "application/ld+json"
    And I send a "POST" request to "/forms" with body:
    """
    {
      "formType": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestType",
      "successHandler": "Silverback\\ApiComponentBundle\\Tests\\TestBundle\\Form\\TestHandler"
    }
    """
    Then the response status code should be 201

  Scenario: Submit invalid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "" } }
    """
    And I add "Content-Type" header equal to "application/ld+json"
    Then the response status code should be 406

  @dropSchema
  Scenario: Submit valid form
    When I send a "POST" request to "/forms/1/submit" with body:
    """
    { "test": { "name": "Your Name Here" } }
    """
    And I add "Content-Type" header equal to "application/ld+json"
    Then the response status code should be 200
