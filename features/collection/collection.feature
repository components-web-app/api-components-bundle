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
        "resourceIri": "/page_data/page_datas",
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
      | resourceIri   |
      | <resourceIri> |
    Then the response status code should be 422
    And the JSON should be valid according to the schema file "validation_errors_object.schema.json"
    Examples:
      | resourceIri |
      | null        |
      | /invalid    |
      | /           |

  @loginUser
  Scenario: I can delete a collection component
    Given there is a Collection resource
    When I send a "DELETE" request to the resource "collection"
    Then the response status code should be 204

  @loginUser
  Scenario: I can get a collection and the default pagination is enabled
    Given there are 2 DummyComponent resources
    And there is a Collection resource
    When I send a "DELETE" request to the resource "dummy_component_0"
    Then the response status code should be 204
    And the resource "collection" should be purged from the cache

  @loginUser
  Scenario: I can get a collection and the default pagination is enabled
    Given there are 50 DummyComponent resources
    And there is a Collection resource
    When I send a "GET" request to the resource "collection"
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
        "perPage": 3
    }
    """
    Then the response status code should be 201
    And the JSON node "collection.hydra:member" should have "3" elements

  @loginUser
  Scenario Outline: Pagination parameter is configured
    Given there are 120 DummyResourceWithPagination resources
    When I send a "GET" request to "/dummy_resource_with_paginations<postfix>"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "<total>" elements
    Examples:
      | total  | postfix           |
      | 10     |                   |
      | 20     | ?perPage=20       |
      | 40     | ?perPage=110      |
      | 120    | ?pagination=false |
      | 0      | ?perPage=0        |

  @loginUser
  Scenario Outline: I can configure component pagination
    Given there are 120 DummyResourceWithPagination resources
    And there is a Collection resource with the resource IRI "/dummy_resource_with_paginations"
    When I send a "GET" request to the resource "collection" and the postfix "<postfix>"
    Then the response status code should be 200
    And the JSON node "collection.hydra:member" should have "<total>" elements
    Examples:
      | total  | postfix           |
      | 10     |                   |
      | 20     | ?perPage=20       |
      | 40     | ?perPage=110      |
      | 120    | ?pagination=false |
      | 0      | ?perPage=0        |

  @loginUser
  Scenario Outline: I have configured my collection correctly to be searched directly without a collection
    Given there are 80 DummyResourceWithFilters resources
    When I send a "GET" request to "/dummy_resource_with_filters<postfix>"
    Then the response status code should be 200
    And the JSON node "hydra:member" should have "<total>" elements
    Examples:
      | total | postfix                      |
      | 17    | ?reference=1                 |
      | 17    | ?pagination=false&reference=1|
      | 30    | ?reference=                  |
      | 80    | ?pagination=false&reference= |
      | 20    | ?perPage=20&reference=       |
      | 1     | ?reference=10                |

  @loginUser
  Scenario Outline: I can have default querystring parameters and filters
    Given there are 80 DummyResourceWithFilters resources
    And there is a Collection resource with the resource IRI "/dummy_resource_with_filters" and default query string parameters
    When I send a "GET" request to the resource "collection" and the postfix "<postfix>"
    Then the response status code should be 200
    And the JSON node "collection.hydra:member" should have "<total>" elements
    Examples:
      | total | postfix                      |
      | 17    |                              |
      | 17    | ?pagination=false            |
      | 40    | ?reference=                  |
      | 80    | ?pagination=false&reference= |
      | 20    | ?perPage=20&reference=       |
      | 1     | ?reference=10                |
