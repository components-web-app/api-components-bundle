Feature: Page resources
  In order to create a page resource
  As an API user
  I can access the page endpoint and perform CRUD

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario Outline: I can create a page
    Given there is a ComponentGroup with 0 components
    And there is a Layout
    And there are 2 Routes
    When I send a "POST" request to "/_/pages" with data:
      | route    | parentRoute      | layout    | ComponentGroup   | reference   | title   | metaDescription   | nested    | uiComponent   | isTemplate   |
      | <route>  | <parentRoute>    | <layout>  | <ComponentGroup> | <reference> | <title> | <metaDescription> | <nested>  | <uiComponent> | <isTemplate> |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"
    Examples:
      | route              | parentRoute            | layout             | ComponentGroup            | reference | title     | metaDescription | nested | uiComponent      | isTemplate |
      | resource[route_0]  | resource[route_1]      | resource[layout]   | resource[component_group] | home      | Home page | my meta         | false  | myComponent      | true       |
      | null               | null                   | resource[layout]   | null                           | home      | null      | null            | true   | myComponent      | false      |

  @loginUser
  Scenario Outline: The page resource validates correctly
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout    | reference   | nested    | uiComponent   | isTemplate |
      | <layout>  | <reference> | false     | <uiComponent> | false      |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"
    And the JSON node "violations[0].propertyPath" should be equal to "<propertyPath>"
    And the JSON node "violations[0].message" should be equal to "<message>"
    Examples:
      | layout             | reference | propertyPath       | message                                    | uiComponent    |
      | null               | home      | layout             | Please specify a layout.                   | myComponent    |
      | resource[layout]   |           | reference          | Please enter a reference.                  | myComponent    |
      | resource[layout]   | home      | uiComponent        | Please specify a UI component.             |                |

  @loginUser
  Scenario Outline: The page resource returns errors on incorrect data types
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout    | reference   | nested    | isTemplate |
      | <layout>  | <reference> | <nested>  | false      |
    Then the response status code should be 500
    And the JSON should be valid according to the schema file "error.schema.json"
    And the JSON node "description" should be equal to the string '<message>'
    Examples:
      | layout             | reference | nested | message                                                               |
      | resource[layout]   | home      | null   | The type of the "nested" attribute must be "bool", "NULL" given.      |
      | resource[layout]   | null      | true   | The type of the "reference" attribute must be "string", "NULL" given. |

  @loginAdmin
  Scenario: I can delete a page
    Given there is a Page
    When I send a "DELETE" request to the resource "page"
    Then the response status code should be 204

  @loginAdmin
  Scenario: The page resources can be filtered by reference
    Given there is a Page with the reference "primary"
    And there is a Page with the reference "secondary"
    When I send a "GET" request to "/_/pages?reference=primary"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "1" elements
    And the JSON node "hydra:member[0].reference" should be equal to "primary"

  @loginAdmin
  Scenario: The page resources can be filtered by title
    Given there is a Page with the reference "primary" and with the title "primary"
    And there is a Page with the reference "secondary" and with the title "secondary"
    When I send a "GET" request to "/_/pages?title=primary"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "1" elements
    And the JSON node "hydra:member[0].reference" should be equal to "primary"

  @loginAdmin
  Scenario: The page resources can be filtered by ui component
    Given there is a Page with the reference "primary" and with the uiComponent "primary"
    And there is a Page with the reference "secondary" and with the uiComponent "secondary"
    When I send a "GET" request to "/_/pages?uiComponent=primary"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "1" elements
    And the JSON node "hydra:member[0].reference" should be equal to "primary"
