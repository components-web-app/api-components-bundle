Feature: Page resources
  In order to create a page resource
  As an API user
  I can access the page endpoint and perform CRUD

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario Outline: I can create a page
    Given there is a ComponentCollection with 0 components
    And there is a Layout
    And there are 2 Routes
    When I send a "POST" request to "/_/pages" with data:
      | route    | parentRoute      | layout    | componentCollection   | reference   | title   | metaDescription   | nested    |
      | <route>  | <parentRoute>    | <layout>  | <componentCollection> | <reference> | <title> | <metaDescription> | <nested>  |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"
    Examples:
      | route              | parentRoute            | layout              | componentCollection             | reference | title     | metaDescription | nested |
      | component[route_0] | component[route_1]     | component[layout]   | component[component_collection] | home      | Home page | my meta         | false  |
      | null               | null                   | component[layout]   | null                            | home      | null      | null            | true   |

  @loginUser
  Scenario Outline: The page resource validates properly
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout    | reference   | nested    |
      | <layout>  | <reference> | false     |
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"
    And the JSON node "violations[0].propertyPath" should be equal to "<propertyPath>"
    And the JSON node "violations[0].message" should be equal to "<message>"
    Examples:
      | layout             | reference | propertyPath       | message                                    |
      | null               | home      | layout             | Please specify a layout.                   |
      | component[layout]  |           | reference          | Please enter a reference.                  |

  @loginUser
  Scenario Outline: The page resource returns errors on incorrect data types
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout    | reference   | nested    |
      | <layout>  | <reference> | <nested>  |
    Then the response status code should be 500
    And the JSON should be valid according to the schema file "error.schema.json"
    And the JSON node "hydra:description" should be equal to the string '<message>'
    Examples:
      | layout             | reference | nested | message                                                               |
      | component[layout]  | home      | null   | The type of the "nested" attribute must be "bool", "NULL" given.      |
      | component[layout]  | null      | true   | The type of the "reference" attribute must be "string", "NULL" given. |

  @loginUser
  Scenario: I can delete a page
    Given there is a Page
    When I send a "DELETE" request to the component "page"
    Then the response status code should be 204
