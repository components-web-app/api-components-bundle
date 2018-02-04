Feature: Website
  In order to create a website
  As an API user
  I can perform operations to create entities down to a page/content level

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: I need a layout for the website
    When I send a "POST" request to "/layouts" with body:
    """
    {
      "default": true
    }
    """
    Then the response status code should be 201
    And save the entity id as layout
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"
    And the JSON node "default" should be true

  Scenario: I need a page on the website
    Given the json variable page_post is:
    """
    {
      "title": "Page Title",
      "metaDescription": "Page Meta Description"
    }
    """
    And the node layout of the json variable page_post is equal to the variable layout
    When I send a "POST" request to "/pages" with the json variable page_post as the body
    Then the response status code should be 201
    And save the entity id as page
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_content.json"
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/page.json"

  Scenario: I need a route to access the page on the website
    Given the json variable route_post is:
    """
    {
      "route": "/"
    }
    """
    And the node content of the json variable route_post is equal to the variable page
    When I send a "POST" request to "/routes" with the json variable route_post as the body
    Then the response status code should be 201
    And save the entity id as route
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/route.json"
    And the JSON node "route" should be equal to the string "/"

#  Scenario: Create navbar
#    When I send a "POST" request to "/nav_bars" with body:
#    """
#    {}
#    """
#    Then the response status code should be 201
#    And save the entity id as navbar
#    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav.json"
#
#  Scenario: Create navbar item
#    When I send a "POST" request to "/nav_bar_items" with body:
#    """
#    {
#      "label": "Item Label",
#      "route": "/",
#      "fragment": null,
#      "child": null
#    }
#    """
#    Then the response status code should be 201
#    And save the entity id as navbar_item
#    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav.json"
#    And the JSON node "label" should be equal to the string "Item Label"
#
#  Scenario: Get a default layout
#    When I send a "GET" request to "/layouts/default"
#    Then the response status code should be 200
#    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"
#
#  Scenario: Update layout
#    When I send a "PUT" request to the entity layout with body:
#    """
#    {
#      "default": false
#    }
#    """
#    Then the response status code should be 200
#    And the JSON should be valid according to the schema "features/bootstrap/json-schema/layout.json"
#    And the JSON node "default" should be false

  Scenario: I need to delete a layout
    When I send a "DELETE" request to the entity layout
    Then the response status code should be 204

  Scenario: I need to delete a page
    When I send a "DELETE" request to the entity page
    Then the response status code should be 204

  @dropSchema
  Scenario: After I have deleted a page the route should no longer exist
    When I send a "GET" request to "/routes"
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/empty_collection.json"
