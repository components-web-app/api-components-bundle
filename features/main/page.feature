Feature: Page resources
  In order to create a page resource
  As an API user
  I can access the page endpoint and perform CRUD

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a page
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | uiComponent | isTemplate |
      | resource[layout] | home      | myComponent | false      |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"

  @loginUser
  Scenario: I can create a page with a parent Page
    Given there is a Layout
    And there is a Page
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | parentPage     | uiComponent | isTemplate |
      | resource[layout] | child     | resource[page] | myComponent | true       |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"
    And the JSON node "parentPage" should be equal to the IRI of the resource "page"

  @loginUser
  Scenario: I can create a page with a parent PageData
    Given there is a Layout
    And there is an empty PageData resource
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | parentPageData      | uiComponent | isTemplate |
      | resource[layout] | child     | resource[page_data] | myComponent | true       |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"
    And the JSON node "parentPageData" should be equal to the IRI of the resource "page_data"

  @loginAdmin
  Scenario: I can patch a page to set a parent Page
    Given there is a Page
    And there is a child Page with a Layout
    When I send a "PATCH" request to the resource "child_page" with data:
      | parentPage     |
      | resource[page] |
    Then the response status code should be 200
    And the JSON node "parentPage" should be equal to the IRI of the resource "page"

  @loginUser
  Scenario: I cannot set both a parent Page and a parent PageData on a page
    Given there is a Layout
    And there is a Page
    And there is an empty PageData resource
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | parentPage     | parentPageData      | uiComponent | isTemplate |
      | resource[layout] | child     | resource[page] | resource[page_data] | myComponent | true       |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginUser
  Scenario Outline: The page resource validates correctly
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout    | reference   | uiComponent   | isTemplate |
      | <layout>  | <reference> | <uiComponent> | false      |
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
      | layout    | reference   | isTemplate |
      | <layout>  | <reference> | false      |
    Then the response status code should be 500
    And the JSON should be valid according to the schema file "error.schema.json"
    And the JSON node "description" should be equal to the string '<message>'
    Examples:
      | layout             | reference | message                                                               |
      | resource[layout]   | null      | The type of the "reference" attribute must be "string", "NULL" given. |

  @loginAdmin
  Scenario: I can delete a page
    Given there is a Page
    When I send a "DELETE" request to the resource "page"
    Then the response status code should be 204
