Feature: API Platform's default exception statuses survive the bundle's own mappings
  In order to get a client error rather than a server error for a bad request
  As an API user
  I need API Platform's default exception-to-status mappings to apply alongside the bundle's

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: A relation given an invalid IRI is a 400, from API Platform's serializer mapping
    Given there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with body:
    """
    {"componentGroup": "/_/not_a_resource/123", "component": "/component/dummy_components/abc"}
    """
    Then the response status code should be 400
    And the JSON node "detail" should contain "Invalid IRI"

  @loginAdmin
  Scenario: An application's more specific mapping still wins over API Platform's default
    Given there is a ComponentGroup with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | sortValue    |
      | resource[component_group] | resource[dummy_component] | not-a-number |
    Then the response status code should be 422
