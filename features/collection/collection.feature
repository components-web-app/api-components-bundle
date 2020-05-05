Feature: A Collection component resource
  In order to get a collection of other components for my web application
  As an API user
  I need to be able to perform CRUD operations on the collection component and implement configuration

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: I can create a collection component
    When I send a "POST" request to "/component/collections" with body:
    """
    {
        "resourceIri": "/component/dummy_components",
        "perPage": 20,
        "defaultQueryParameters": {
          "search": "something",
          "orderBy": "anotherThing"
        }
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "collection.schema.json"

  @loginUser
  Scenario Outline: I cannot create a collection component with an invalid Resource IRI
    When I send a "POST" request to "/component/collections" with data:
     | resourceIri    |
     | <resourceIri>  |
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"
    Examples:
      | resourceIri |
      | null        |
      | /invalid    |
      | /           |

  @loginUser
  Scenario: I can delete a collection component
    Given there is a Collection resource
    When I send a "DELETE" request to the component "collection"
    Then the response status code should be 204


  @loginUser
  Scenario: I can get a collection and the default pagination is enabled
    Given there are 50 DummyComponent resources
    And there is a Collection resource
    When I send a "GET" request to the component "collection"
    Then the response status code should be 200
    And the JSON node "collection.hydra:member" should have "30" elements
    And the JSON node "collection.hydra:totalItems" should be equal to "50"
    And the JSON node "collection.@id" should be equal to "/component/dummy_components"
    And the JSON node "collection.@type" should be equal to "hydra:Collection"

  @loginUser
  Scenario: I can get a collection component with perPage configured
    Given there are 50 DummyComponent resources
    When I send a "POST" request to "/component/collections" with body:
    """
    {
        "resourceIri": "/component/dummy_components",
        "perPage": 10
    }
    """
    Then the response status code should be 201
    And the JSON node "collection.hydra:member" should have "10" elements

  @loginUser
  Scenario Outline: I can configure component pagination
    Given there are 50 DummyResourceWithPagination resources
    And there is a Collection resource with the resource IRI "/dummy_resource_with_paginations"
    When I send a "GET" request to the component "collection" and the postfix "<postfix>"
    Then the response status code should be 200
    And the JSON node "collection.hydra:member" should have "<total>" elements
    Examples:
      | total    | postfix              |
      | 10       |                      |
      | 20       | ?perPage=20          |
      | 40       | ?perPage=90          |
      | 50       | ?pagination=false    |
      | 50       | ?perPage=0           |
