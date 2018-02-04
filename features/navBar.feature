Feature: Layout Nav Bars
  In order to manage layout navigation bars
  As an API user
  I can perform all known requests with customisations and receive expected responses

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: I want a navigation bar
    When I send a POST request to "/nav_bars" with body:
    """
    {}
    """
    Then the response status code should be 201
    And save the entity id as navbar
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav.json"

  Scenario: Create navbar item
    Given the json variable nav_bar_item_post is:
    """
    {
      "label": "Fragment Item Label",
      "route": null,
      "fragment": "#Frag",
      "child": null
    }
    """
    And the node navigation of the json variable nav_bar_item_post is equal to the variable navbar
    When I send a "POST" request to "/nav_bar_items" with the json variable nav_bar_item_post as the body
    Then the response status code should be 201
    And save the entity id as navbar_item
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav_item.json"

  Scenario: I want to add a layout with the new nav bar
    Given the json variable layout_item_post is:
    """
    {}
    """
    And the node nav_bar of the json variable layout_item_post is equal to the variable navbar
    When I send a "POST" request to "/layouts" with the json variable layout_item_post as the body
    Then the response status code should be 201
    And save the entity id as layout

  Scenario: I need to delete a layout
    When I send a "DELETE" request to the entity layout
    Then the response status code should be 204

  Scenario: I want to get the navbar which should still exist
    When I send a GET request to the entity navbar
    Then the response status code should be 200

  Scenario: I want to delete the navbar
    When I send a DELETE request to the entity navbar
    Then the response status code should be 204

  @dropSchema
  Scenario: No nab bar item orphans should exist
    When I send a GET request to the entity navbar_item
    Then the response status code should be 404
