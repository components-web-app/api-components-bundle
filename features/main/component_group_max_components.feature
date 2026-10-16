Feature: A maximum number of components in a component group
  In order to keep a design that holds a fixed number of components
  As an API user
  I can limit how many components a component group holds

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: A component group is created with a maximum number of components
    When I send a "POST" request to "/_/component_groups" with body:
    """
    {
      "reference": "header_button",
      "location": "header",
      "maxComponents": 1
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_group.schema.json"
    And the JSON node "maxComponents" should be equal to the number 1

  Scenario: The maximum number of components is readable with the component group
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the JSON node "maxComponents" should be equal to the number 1

  Scenario: A component group with no maximum has a null maxComponents
    Given there is a ComponentGroup with 1 components
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the JSON node "maxComponents" should be null

  @loginUser
  Scenario Outline: The maximum number of components must be a positive integer or null
    Given there is a ComponentGroup with 0 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "component_group" with body:
    """
    {"maxComponents": <maxComponents>}
    """
    Then the response status code should be <status>
    Examples:
      | maxComponents | status |
      | 0             | 422    |
      | -1            | 422    |
      | 1             | 200    |
      | null          | 200    |

  @loginUser
  Scenario: A component cannot be added to a full component group
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentGroup"
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."
    And there should be 1 ComponentPosition resources

  @loginUser
  Scenario: A component can be added to a component group below its maximum
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 2
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201

  @loginUser
  Scenario: A component group with no maximum takes any number of components
    Given there is a ComponentGroup with 5 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201

  @loginUser
  Scenario: A dynamic page data position cannot be added to a full component group
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty | pageDataClass                                                                           |
      | resource[component_group] | component        | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 422
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."

  @loginUser
  Scenario: A dynamic page data position counts towards the maximum
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has a pageDataProperty position
    And the ComponentGroup "component_group" has the maxComponents 1
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 422

  @loginUser
  Scenario: A published component with a draft counts once
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup holds a published DummyPublishableComponent with a draft
    And the ComponentGroup "component_group" has the maxComponents 2
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201

  @loginUser
  Scenario: A position hidden by the allowed components does not count towards the maximum
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup has a RestrictedComponent position with the sortValue 5
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    And the ComponentGroup "component_group" has the maxComponents 2
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201

  @loginUser
  Scenario: A component cannot be created straight into a full component group
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    When I send a "POST" request to "/component/dummy_components" with data:
      | componentPositions                                             |
      | json_decode([{"componentGroup": "resource[component_group]"}]) |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentPositions[0].componentGroup"
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."
    And there should be 1 ComponentPosition resources

  @loginUser
  Scenario: A component can be created straight into a component group below its maximum
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 2
    When I send a "POST" request to "/component/dummy_components" with data:
      | componentPositions                                             |
      | json_decode([{"componentGroup": "resource[component_group]"}]) |
    Then the response status code should be 201
    And there should be 2 ComponentPosition resources

  @loginUser
  Scenario: A component in a full component group can still be edited
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "component_0" with body:
    """
    {"uiComponent": "Another"}
    """
    Then the response status code should be 200

  @loginUser
  Scenario: A position cannot be moved into a full component group
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 1
    And there is another ComponentGroup with 1 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "other_position_0" with data:
      | componentGroup            |
      | resource[component_group] |
    Then the response status code should be 422
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."

  @loginUser
  Scenario: A position can be moved into a component group below its maximum
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup "component_group" has the maxComponents 2
    And there is another ComponentGroup with 1 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "other_position_0" with data:
      | componentGroup            |
      | resource[component_group] |
    Then the response status code should be 200

  @loginUser
  Scenario: A position can be moved within a full component group
    Given there is a ComponentGroup with 2 components
    And the ComponentGroup "component_group" has the maxComponents 2
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_0" with data:
      | after                |
      | resource[position_1] |
    Then the response status code should be 200
    And the ComponentPosition sort values should be:
      | position   | sortValue |
      | position_1 | 1         |
      | position_0 | 2         |

  @loginUser
  Scenario: Lowering the maximum below the components a group holds keeps them all
    Given there is a ComponentGroup with 3 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "component_group" with body:
    """
    {"maxComponents": 1}
    """
    Then the response status code should be 200
    And the JSON node "maxComponents" should be equal to the number 1
    And the JSON node "componentPositions" should have 3 elements
    And there should be 3 ComponentPosition resources
    And there should be 3 DummyComponent resources

  @loginUser
  Scenario: A group holding more components than its maximum takes no more
    Given there is a ComponentGroup with 3 components
    And the ComponentGroup "component_group" has the maxComponents 1
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component             |
      | resource[component_group] | resource[component_0] |
    Then the response status code should be 422
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."

  @loginUser
  Scenario: A position in a group over its maximum can still be moved within it
    Given there is a ComponentGroup with 3 components
    And the ComponentGroup "component_group" has the maxComponents 1
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_2" with data:
      | before               |
      | resource[position_0] |
    Then the response status code should be 200
