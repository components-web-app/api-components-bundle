Feature: Component positions
  In order to position components
  As an API user
  I can add a component into a collection

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: Create a new component position resource
    Given there is a ComponentGroup with 0 components
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup             | component                  |
      | resource[component_group]  | resource[dummy_component] |
    Then the response status code should be 201
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the Mercure message for component group should contain timestamped fields
    And the Mercure message for component group should contain 1 component position

  Scenario: sortCollection is not included in a component position response
    Given there is a ComponentGroup with 1 components
    When I send a "GET" request to the resource "position_0"
    Then the response status code should be 200
    And the JSON node "sortCollection" should not exist

  @loginUser
  Scenario Outline: I can restrict which components are permitted to be inside a component collection and a component that must be specifically defined as being allowed to pass validation
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has the allowedComponent "<allowedComponent>"
    And there is a DummyComponent
    And there is a RestrictedComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup   | component   |
      | <componentGroup> | <component> |
    Then the response status code should be <status>
    Examples:
      | component                       | componentGroup             | status | allowedComponent                 |
      | resource[dummy_component]       | resource[component_group]  | 422    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_group]  | 201    | /component/restricted_components |
      | resource[restricted_component]  | resource[component_group]  | 422    |                                  |
      | resource[dummy_component]       | resource[component_group]  | 201    |                                  |

  @loginUser
  Scenario: ComponentPosition sortValue auto-increments
    Given there is a ComponentGroup with 1 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                 | component              |
      | resource[component_group] | resource[component_0] |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And the JSON should be valid according to the schema file "component_position.schema.json"

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 3 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 1           |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_2"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 4 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_2" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 3           |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_3"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2

  @loginUser
  Scenario: ComponentPosition sortValue will be updated on subsequent pre-existing component positions
    Given there is a ComponentGroup with 4 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_2" with data:
      | componentGroup                  | component              | sortValue   |
      | resource[component_group]  | resource[component_0]  | 1           |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON should be valid according to the schema file "component_position.schema.json"
    And the JSON node "sortValue" should be equal to the number 0

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2

    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_3"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 3

  @loginUser
  Scenario: Inserting a ComponentPosition at a sortValue with no collision does not shift existing positions
    Given there is a ComponentGroup with 3 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component             | sortValue |
      | resource[component_group] | resource[component_0] | 5         |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 5
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_0"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 0
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_1"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1
    And I add "Content-Type" header equal to "application/ld+json"
    And I add "Accept" header equal to "application/ld+json"
    And I send a "GET" request to the resource "position_2"
    And the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 2

  @loginUser
  Scenario: Moving a ComponentPosition that collides with no other position changes only the positions in the moved range
    Given there is a ComponentGroup with 5 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_1" with data:
      | sortValue |
      | 3         |
    Then the response status code should be 200
    And the ComponentPosition sort values should be:
      | position   | sortValue |
      | position_0 | 0         |
      | position_1 | 3         |
      | position_2 | 1         |
      | position_3 | 2         |
      | position_4 | 4         |
    And Mercure updates should have been published for exactly the ComponentPositions "position_1, position_2, position_3"

  @loginUser
  Scenario: Moving a ComponentPosition into another group at an occupied sortValue resolves to unique sort values
    Given there is a ComponentGroup with 3 components
    And there is another ComponentGroup with 2 components
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "other_position_0" with data:
      | componentGroup            | sortValue |
      | resource[component_group] | 1         |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1
    And the ComponentPosition sort values should be:
      | position         | sortValue |
      | position_0       | 0         |
      | other_position_0 | 1         |
      | position_1       | 2         |
      | position_2       | 3         |

  @loginUser
  Scenario: Moving a ComponentPosition within a group that already holds a duplicate sortValue resolves to unique sort values
    Given there is a ComponentGroup with 4 components
    And the ComponentPosition "position_2" has the sortValue 1
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "position_0" with data:
      | sortValue |
      | 3         |
    Then the response status code should be 200
    And the ComponentPosition sort values should be:
      | position   | sortValue |
      | position_1 | 0         |
      | position_2 | 1         |
      | position_3 | 2         |
      | position_0 | 3         |

  @loginUser
  Scenario: Cannot create a dynamic position with pageDataProperty but no pageDataClass
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty |
      | resource[component_group] | component        |
    Then the response status code should be 422

  @loginUser
  Scenario: Cannot create a dynamic position with pageDataClass but no pageDataProperty
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataClass                                                                           |
      | resource[component_group] | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 422

  @loginUser
  Scenario: Cannot create a dynamic position with a pageDataClass that is not a known PageData resource
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty | pageDataClass         |
      | resource[component_group] | component        | App\NotAPageDataClass |
    Then the response status code should be 422

  @loginUser
  Scenario: Cannot create a dynamic position where pageDataProperty is not a component-typed property on the pageDataClass
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty | pageDataClass                                                                           |
      | resource[component_group] | notAProperty     | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 422

  @loginUser
  Scenario: Cannot create a dynamic position where the resolved component type is not in allowedComponents
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty     | pageDataClass                                                                           |
      | resource[component_group] | publishableComponent | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 422

  @loginUser
  Scenario: Can create a dynamic position with a valid pageDataClass and pageDataProperty
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty | pageDataClass                                                                           |
      | resource[component_group] | component        | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 201

  @loginUser
  Scenario: Can create a dynamic position where the component type matches allowedComponents
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty | pageDataClass                                                                           |
      | resource[component_group] | component        | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent |
    Then the response status code should be 201

  @loginUser
  Scenario: Cannot create a dynamic position resolving to an explicitAllowOnly component type in a group without allowedComponents
    Given there is a ComponentGroup with 0 components
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty    | pageDataClass                                                                                     |
      | resource[component_group] | restrictedComponent | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRestrictedComponent |
    Then the response status code should be 422

  @loginUser
  Scenario: Can create a dynamic position resolving to an explicitAllowOnly component type when the group explicitly allows it
    Given there is a ComponentGroup with 0 components
    And the ComponentGroup has the allowedComponent "/component/restricted_components"
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | pageDataProperty    | pageDataClass                                                                                     |
      | resource[component_group] | restrictedComponent | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRestrictedComponent |
    Then the response status code should be 201

  @loginAdmin
  Scenario: An admin can read pageDataClass from a dynamic position
    Given there is a PageData resource with the route path "/page-data"
    When I send a "GET" request to the resource "component_position"
    Then the response status code should be 200
    And the JSON node "pageDataClass" should be equal to "Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent"
