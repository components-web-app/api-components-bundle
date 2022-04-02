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
    And the JSON node "_metadata.page_data_metadata.properties" should have 2 elements
    And the JSON node "_metadata.page_data_metadata.properties[0].property" should be equal to "component"
    And the JSON node "_metadata.page_data_metadata.properties[0].componentShortName" should be equal to "DummyComponent"

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
    And the JSON node "_metadata.static_component" should be null

  Scenario: Populating the component from a page data property
    Given there is a PageData resource with the route path "/page-data"
    And I add "path" header equal to the resource "page_data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"