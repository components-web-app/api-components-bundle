Feature: Page data metadata
  In order to know what page data resources and properties are available
  As an API user
  I can access a page data endpoints

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: I can get all page data metadata
    When I send a "GET" request to "/_/page_data_metadatas"
    Then the response status code should be 200
    And the JSON node "@context.properties" should be equal to "PageDataMetadata/properties"
    And the JSON node "hydra:member" should have 4 elements
    # the order is not consistent... and doesn't really need to be - if no cache vs if cached
#    And the JSON node "hydra:member[0].properties" should have 0 element
#    And the JSON node "hydra:member[1].properties" should have 1 element
#    And the JSON node "hydra:member[1].properties[0].property" should be equal to "component"
#    And the JSON node "hydra:member[1].properties[0].componentClass" should be equal to "DummyComponent"

  Scenario: I can get a single page data endpoint
    When I send a "GET" request to "/_/page_data_metadatas/Silverback%5CApiComponentsBundle%5CTests%5CFunctional%5CTestBundle%5CEntity%5CPageDataWithComponent"
    Then the response status code should be 200
    And the JSON node "properties" should have 2 element
    And the JSON node "properties[0].property" should be equal to "component"
    And the JSON node "properties[0].componentShortName" should be equal to "DummyComponent"
    And the JSON node "properties[0].componentClass" should not exist
