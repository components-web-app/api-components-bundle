Feature: Layout resources
  In order to create a layout for my application
  As an API user
  I can add create a layout resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a layout
    When I send a "POST" request to "/_/layouts" with body:
    """
    {
      "reference": "primary",
      "uiComponent": "PrimaryLayout",
      "classNames": ["has-border"]
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "layout.schema.json"

  @loginUser
  Scenario Outline: The layout resource validates properly
    Given there is a Layout with the reference "primary"
    When I send a "POST" request to "/_/layouts" with data:
      | reference   | uiComponent    |
      | <reference> | <uiComponent>  |
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"
    And the JSON node "violations[0].propertyPath" should be equal to "<propertyPath>"
    And the JSON node "violations[0].message" should be equal to "<message>"
    Examples:
      | reference    | uiComponent    | propertyPath      | message                                            |
      |              | PrimaryLayout  | reference         | Please enter a reference.                          |
      | primary      | PrimaryLayout  | reference         | There is already a Layout with that reference.     |
      | new          | null           | uiComponent       | You must define the uiComponent for this resource. |

  @loginUser
  Scenario: I can delete a route
    Given there is a Layout
    When I send a "DELETE" request to the component "layout"
    Then the response status code should be 204
