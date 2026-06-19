Feature: Dynamic pages
  In order to populate dynamic pages
  As an API user
  I can fetch a dynamic page and the components will be created

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: When I get a page data resource, it will be returned with the components generated
    Given there is an empty PageData resource
    When I send a "GET" request to the resource "page_data"
    Then the response status code should be 200
    And the JSON node "_metadata.pageDataMetadata.properties" should have 2 elements
    And the JSON node "_metadata.pageDataMetadata.properties[0].property" should be equal to "component"
    And the JSON node "_metadata.pageDataMetadata.properties[0].componentShortName" should be equal to "DummyComponent"

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to "http://example.com/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "_metadata.staticComponent" should be null

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to the resource "page_data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"

  @loginAdmin
  Scenario: When a dynamic component is deleted, related component positions should be purged from the cache
    Given there is a DummyComponent in PageData and a Position
    When I send a "DELETE" request to the resource "page_data_component"
    Then the response status code should be 204
    And the resource "component_position" should exist
    And the resource "page_data" should be purged from the cache
    And the resource "page_data_component" should be purged from the cache
    And the resource "component_position" should be purged from the cache

  @loginAdmin
  Scenario: When a dynamic component is deleted, and exists only in page data, the dynamic position should be cleared from the cache
    Given there is a DummyComponent in PageData
    When I send a "DELETE" request to the resource "page_data_component"
    Then the response status code should be 204
    And the resource "component_position" should exist
    And the resource "page_data" should be purged from the cache
    And the resource "page_data_component" should be purged from the cache
    And the resource "component_position" should be purged from the cache

  @loginAdmin
  Scenario: When a dynamic component is added, related component positions should be purged from the cache
    Given there is a DummyComponent in a Position with an empty PageData
    When I patch the PageData with the property "component" and resource "page_data_component"
    Then the response status code should be 200
    And the resource "component_position" should exist
    And the resource "component_position" should be purged from the cache

  @loginAdmin
  Scenario: An admin can fetch a pageDataProperty position without a path header and the component slot is not resolved
    Given there is a PageData resource with the route path "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON node "pageDataProperty" should be equal to "component"
    And the JSON node "component" should be null

  @loginAdmin
  Scenario: Fetching a pageDataProperty position with a path that has no matching page data returns the position without a component
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to "/unknown-path"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON node "pageDataProperty" should be equal to "component"
    And the JSON node "component" should be null

  Scenario: A published pageDataProperty component is returned for anonymous users
    Given there is a PageData resource with a published component in a pageDataProperty position and the route path "/page-data"
    And I add "path" header equal to "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON node "component" should match the regex "/\/component\/dummy_publishable_components\/[a-z0-9\-]+/"

  Scenario: A draft pageDataProperty component is not returned for anonymous users
    Given there is a PageData resource with a draft component in a pageDataProperty position and the route path "/page-data"
    And I add "path" header equal to "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON node "component" should be null
