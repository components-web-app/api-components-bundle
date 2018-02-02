Feature: NavBar
  In order to interact with NavBar entities
  As a website user
  I can use the API to perform CRUD operations on NavBars

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Create page
    When I send a "POST" request to "/pages" with body:
    """
    {}
    """
    Then the response status code should be 201
    And save the entity id as page
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav.json"

  Scenario: Create route
    When I send a "POST" request to the sub-resource "/routes" of page with body:
    """
    {
      "route": "/",
      "fragment": null,
      "redirect": null
    }
    """
    Then the response status code should be 201
    And save the entity id as route
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/abstract_nav.json"

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

  Scenario: Delete page
    When I send a "DELETE" request to the entity page
    Then the response status code should be 204

  @dropSchema
  Scenario: Route cascaded delete
    When I send a "GET" request to "/routes"
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/empty_collection.json"
