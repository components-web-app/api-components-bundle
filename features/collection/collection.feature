Feature: A Collection component resource
  In order to get a collection of other components for my web application
  As an API user
  I need to be able to perform CRUD operations on the collection component and implement configuration

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a collection component
    When I send a "POST" request to "/component/collections" with body:
    """
    {
        "resourceIri": "/component/dummy_components"
    }
    """
    Then the response status code should be 201
    And the JSON should be equal to:
    """
    {}
    """

  @loginUser
  Scenario Outline: I cannot create a collection component with an invalid Resource IRI
    When I send a "POST" request to "/component/collections" with body:
    """
    {
        "resourceIri": "<resourceIri>"
    }
    """
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"
    Examples:
      | resourceIri |
      | null        |
      | /invalid    |
      | /           |

  @loginUser
  @wip
  Scenario: I can read a collection component

  @loginUser
  @wip
  Scenario: I can update a collection component

  @loginUser
  @wip
  Scenario: I can delete a collection component

  @loginUser
  @wip
  Scenario: I can configure pagination on the collection component

  @loginUser
  @wip
  Scenario: I can configure and apply API Filters on a collection component
