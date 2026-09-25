Feature: A component held by a page data property typed as a parent class of the component
  In order to use one page data property for several component types
  As an application developer
  I need a component held by a parent-typed page data property to be treated as used by that page data

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: The component's usage counts the page data property
    Given there is a DummyComponent held only by a page data property typed as its parent class
    When I send a "GET" request to the resource "dummy_component" and the postfix "/usage"
    Then the response status code should be 200
    And the JSON node "positionCount" should be equal to 0
    And the JSON node "pageDataCount" should be equal to 1
    And the JSON node "total" should be equal to 1

  Scenario: The clean orphaned command keeps the component
    Given there is a DummyComponent held only by a page data property typed as its parent class
    When I run the clean orphaned command
    Then the command should have succeeded
    And the resource "dummy_component" should exist
    And the page data "parent_typed_page_data" should still hold the resource "dummy_component"

  Scenario: The component is public when the page data is on a live route
    Given there is a DummyComponent held only by a page data property typed as its parent class with the route path "/parent-typed"
    When I send a "GET" request to the resource "dummy_component"
    Then the response status code should be 200

  Scenario: The component is not public when the page data has no route
    Given there is a DummyComponent held only by a page data property typed as its parent class
    When I send a "GET" request to the resource "dummy_component"
    Then the response status code should be 401

  Scenario: The component is not public when the page data's route is not live yet
    Given there is a DummyComponent held only by a page data property typed as its parent class with the route path "/parent-typed"
    And the Route "/parent-typed" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "dummy_component"
    Then the response status code should be 401

  @loginAdmin
  Scenario: Editing the component purges the page data and publishes it to Mercure
    Given there is a DummyComponent held only by a page data property typed as its parent class with the route path "/parent-typed"
    When I send a "PATCH" request to the resource "dummy_component" with body:
      """
      {"uiComponent": "Changed"}
      """
    Then the response status code should be 200
    And the resource "parent_typed_page_data" should be purged from the cache
    And a Mercure update should have been published for the resource "parent_typed_page_data"
