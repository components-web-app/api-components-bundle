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
      "uiClassNames": ["has-border"]
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "layout.schema.json"
    And the JSON node reference should exist
    And the JSON node uiComponent should exist
    And the JSON node uiClassNames should exist

  @loginUser
  Scenario Outline: The layout resource validates properly
    Given there is a Layout with the reference "primary"
    When I send a "POST" request to "/_/layouts" with data:
      | reference   | uiComponent    |
      | <reference> | <uiComponent>  |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"
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
    When I send a "DELETE" request to the resource "layout"
    Then the response status code should be 204

  @loginUser
  Scenario: The layout resources can be filtered by reference
    Given there is a Layout with the reference "primary"
    And there is a Layout with the reference "secondary"
    When I send a "GET" request to "/_/layouts"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "2" elements

  @loginUser
  Scenario: The layout resources can be filtered by reference
    Given there is a Layout with the reference "primary"
    And there is a Layout with the reference "secondary"
    When I send a "GET" request to "/_/layouts?reference=primary"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "1" element

  @loginUser
  Scenario: The layout resources can be ordered ascending by reference
    Given there is a Layout with the reference "1"
    And there is a Layout with the reference "2"
    When I send a "GET" request to "/_/layouts?order[reference]=asc"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "2" elements
    And the JSON node "hydra:member[0].reference" should be equal to "1"
    And the JSON node "hydra:member[1].reference" should be equal to "2"

  @loginUser
  Scenario: The layout resources can be ordered descending by reference
    Given there is a Layout with the reference "1"
    And there is a Layout with the reference "2"
    When I send a "GET" request to "/_/layouts?order[reference]=desc"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "2" elements
    And the JSON node "hydra:member[0].reference" should be equal to "2"
    And the JSON node "hydra:member[1].reference" should be equal to "1"

  @loginUser
  Scenario: The layout resources can be ordered ascending by createdAt
    Given there is a Layout with the reference "layout_1" and with createdAt "now"
    And there is a Layout with the reference "layout_2" and with createdAt "+10 seconds"
    When I send a "GET" request to "/_/layouts?order[createdAt]=asc"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "2" elements
    And the JSON node "hydra:member[0].reference" should be equal to "layout_1"
    And the JSON node "hydra:member[1].reference" should be equal to "layout_2"

  @loginUser
  Scenario: The layout resources can be ordered descending by createdAt
    Given there is a Layout with the reference "layout_1" and with createdAt "now"
    And there is a Layout with the reference "layout_2" and with createdAt "+10 seconds"
    When I send a "GET" request to "/_/layouts?order[createdAt]=desc"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "2" elements
    And the JSON node "hydra:member[0].reference" should be equal to "layout_2"
    And the JSON node "hydra:member[1].reference" should be equal to "layout_1"

  @loginUser
  Scenario: The layout resources can be filtered by ui components
    Given there is a Layout with the reference "primary" and with the uiComponent "PrimaryLayout"
    And there is a Layout with the reference "secondary" and with the uiComponent "SecondaryLayout"
    When I send a "GET" request to "/_/layouts?uiComponent=PrimaryLayout"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "1" elements
    And the JSON node "hydra:member[0].reference" should be equal to "primary"
