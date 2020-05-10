Feature: Component positions
  In order to position components
  As an API user
  I can add a component into a collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario Outline: When I delete a component collection resource, locations are also deleted but components are not
    Given there is a ComponentCollection with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection   | component   |
      | <componentCollection> | <component> |
    Then the response status code should be 201
    Examples:
      | component                    | componentCollection             |
      | component[dummy_component]   | component[component_collection] |

  @loginUser
  Scenario Outline: I can restrict which components are permitted to be inside a component collection
    Given there is a ComponentCollection with 0 components
    And the ComponentCollection has the allowedComponent "Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent"
    And there is a DummyComponent
    And there is a RestrictedComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentCollection   | component   |
      | <componentCollection> | <component> |
    Then the response status code should be <status>
    Examples:
      | component                       | componentCollection             | status |
      | component[dummy_component]      | component[component_collection] | 400    |
      | component[restricted_component] | component[component_collection] | 201    |
