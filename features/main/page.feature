Feature: Page resources
  In order to create a page resource
  As an API user
  I can access the page endpoint and perform CRUD

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: An admin can create a page
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | uiComponent | isTemplate |
      | resource[layout] | home      | myComponent | false      |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"

  @loginUser
  Scenario: A non-admin user cannot create a page
    Given there is a Layout
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | uiComponent | isTemplate |
      | resource[layout] | home      | myComponent | false      |
    Then the response status code should be 403

  @loginAdmin
  Scenario: An admin can create a page with a parent Page
    Given there is a Layout
    And there is a Page
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | parentPage     | uiComponent | isTemplate |
      | resource[layout] | child     | resource[page] | myComponent | true       |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "page.schema.json"
    And the JSON node "parentPage" should be equal to the IRI of the resource "page"

  @loginAdmin
  Scenario: An admin can create a page with a parent PageData
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

  @loginAdmin
  Scenario: I cannot set a page as its own parent
    Given there is a Page
    When I send a "PATCH" request to the resource "page" with data:
      | parentPage     |
      | resource[page] |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginAdmin
  Scenario: I cannot create a circular parent chain between two pages
    Given there is a Page
    And there is a page with parent page "page"
    When I send a "PATCH" request to the resource "page" with data:
      | parentPage           |
      | resource[child_page] |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginAdmin
  Scenario: I cannot create a circular parent chain across mixed Page and PageData parents
    Given there is a Page
    And there is a page data with parent page "page"
    When I send a "PATCH" request to the resource "page" with data:
      | parentPageData      |
      | resource[page_data] |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginAdmin
  Scenario: An admin cannot set both a parent Page and a parent PageData on a page
    Given there is a Layout
    And there is a Page
    And there is an empty PageData resource
    When I send a "POST" request to "/_/pages" with data:
      | layout           | reference | parentPage     | parentPageData      | uiComponent | isTemplate |
      | resource[layout] | child     | resource[page] | resource[page_data] | myComponent | true       |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"

  @loginAdmin
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
      | resource[layout]   | null      | reference          | Please enter a reference.                  | myComponent    |
      | resource[layout]   | home      | uiComponent        | Please specify a UI component.             |                |

  @loginAdmin
  Scenario: I can delete a page
    Given there is a Page
    When I send a "DELETE" request to the resource "page"
    Then the response status code should be 204

  @loginAdmin
  Scenario: I can get a resource manifest for a flat PageData by UUID
    Given there is a PageData resource with the route path "/my-route"
    When I send a "GET" request to the resource "page_data_manifest"
    Then the response status code should be 200
    And the JSON node "resource_iris" should have 1 element
    And the JSON node "resource_iris[0][0]" should be equal to the IRI of the resource "page_data"

  @loginAdmin
  Scenario: I can get a resource manifest for a nested PageData by UUID
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    When I send a "GET" request to the resource "page_data_manifest"
    Then the response status code should be 200
    And the JSON node "resource_iris" should have 2 elements
    And the JSON node "resource_iris[1][0]" should be equal to the IRI of the resource "page_data"

  @loginAdmin
  Scenario: I can PATCH a page when componentGroups are included in the request body
    Given there is a valid Page with a ComponentGroup
    When I patch the page with the component group in the request body
    Then the response status code should be 200

  @loginAdmin
  Scenario: I can PATCH a page when embedded componentGroups with positions are included in the request body
    Given there is a valid Page with a ComponentGroup
    When I patch the page with an embedded component group in the request body
    Then the response status code should be 200

  @loginAdmin
  Scenario: The page collection includes parentPage when set
    Given there is a Page
    And there is a page with parent page "page"
    When I send a "GET" request to "/_/pages?order[reference]=asc"
    Then the response status code should be 200
    And the JSON node "member[0].parentPage" should be equal to the IRI of the resource "page"

  @loginAdmin
  Scenario: I can get a resource manifest for a nested Page by UUID
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    When I send a "GET" request to the resource "page_manifest"
    Then the response status code should be 200
    And the JSON node "resource_iris" should have 2 elements
    And the JSON node "resource_iris[1][0]" should be equal to the IRI of the resource "page"
