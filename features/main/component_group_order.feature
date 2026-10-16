Feature: Ordering the positions in a component group
  In order to place components without knowing every other position's value
  As an API user
  I send the order I want and the server chooses the sort values

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  Scenario: An insert after a position with room after it changes no other position
    Given there is a ComponentGroup with the positions "A0 B10 C20"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 1
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 N1 B10 C20"

  @loginUser
  Scenario: An insert after a position with no room after it moves the positions after it along by one
    Given there is a ComponentGroup with the positions "A0 B1 C2"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 N1 B2 C3"

  @loginUser
  Scenario: An insert moves only the positions it runs into
    Given there is a ComponentGroup with the positions "A0 B1 C2 D10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 N1 B2 C3 D10"

  @loginUser
  Scenario: An insert before the first position starts the group at 0
    Given there is a ComponentGroup with the positions "A0 B1"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | before      |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "N0 A1 B2"

  @loginUser
  Scenario: An insert with no anchor goes at the end
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 |
      | resource[component_group] | resource[dummy_component] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 B10 N11"

  @loginUser
  Scenario: An insert after the first of two positions sharing a value removes the tie
    Given there is a ComponentGroup with the positions "A5 B5"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A5 N6 B7"

  @loginUser
  Scenario: An insert before the second of two positions sharing a value removes the tie
    Given there is a ComponentGroup with the positions "A5 B5"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | before      |
      | resource[component_group] | resource[dummy_component] | resource[B] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A5 N6 B7"

  @loginUser
  Scenario: An insert from a client that has not seen another editor's move still goes after its anchor
    Given there is a ComponentGroup with the positions "A0 B10 C20"
    When I send the order "A, C, B" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "A0 C20 B21"
    Given there is a DummyComponent
    And I add "Accept" header equal to "application/ld+json"
    And I add the logged in user's token to the request
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 N1 C20 B21"

  @loginUser
  Scenario Outline: An anchor that is not in the group is a 422
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is another ComponentGroup with the positions "X0"
    And the ComponentPosition "B" has been deleted
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | <anchor>             |
      | resource[component_group] | resource[dummy_component] | resource[<position>] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "<anchor>"
    And the JSON node "violations[0].message" should contain "is not in the component group `collection`"
    And the ComponentGroup "component_group" should hold the positions "A0"
    Examples:
      | anchor | position |
      | after  | X        |
      | before | X        |
      | after  | B        |
      | before | B        |

  @loginUser
  Scenario Outline: An anchor that does not resolve to a position is a 422, never ignored
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | <anchor> |
      | resource[component_group] | resource[dummy_component] | <iri>    |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "<anchor>"
    And the ComponentGroup "component_group" should hold the positions "A0 B10"
    Examples:
      | anchor | iri                                                         |
      | after  | /_/component_positions/00000000-0000-4000-8000-000000000000 |
      | before | /_/component_positions/00000000-0000-4000-8000-000000000000 |
      | after  | /nope                                                       |
      | before | resource[dummy_component]                                   |

  @loginUser
  Scenario Outline: An anchor sent with a sortValue is a 422
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | <anchor>    | sortValue |
      | resource[component_group] | resource[dummy_component] | resource[A] | 1         |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "<anchor>"
    And the JSON node "violations[0].message" should be equal to "Send an anchor or a sortValue, not both: the server chooses the sortValue."
    And the ComponentGroup "component_group" should hold the positions "A0 B10"
    Examples:
      | anchor |
      | after  |
      | before |

  @loginUser
  Scenario: An insert with both anchors is a 422 on before
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       | before      |
      | resource[component_group] | resource[dummy_component] | resource[A] | resource[B] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "before"
    And the JSON node "violations[0].message" should be equal to "Send either after or before, not both."

  @loginUser
  Scenario: A sortValue sent without an anchor is ignored
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | sortValue |
      | resource[component_group] | resource[dummy_component] | 0         |
    Then the response status code should be 201
    And the JSON node "sortValue" should be equal to the number 11
    And I save the JSON node "@id" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 B10 N11"

  @loginUser
  Scenario: A sortValue in a PATCH is ignored
    Given there is a ComponentGroup with the positions "A0 B10"
    When I send a "PATCH" request to the resource "A" with body:
    """
    {"sortValue": 20}
    """
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 0
    And the ComponentGroup "component_group" should hold the positions "A0 B10"

  @loginUser
  Scenario: An anchored insert into a full group is a 422
    Given there is a ComponentGroup with the positions "A0"
    And the ComponentGroup "component_group" has the maxComponents 1
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentGroup"
    And the JSON node "violations[0].message" should be equal to "The component group `collection` is full: it holds at most 1 component."
    And the ComponentGroup "component_group" should hold the positions "A0"

  @loginUser
  Scenario: A component created straight into a group is placed after its anchor
    Given there is a ComponentGroup with the positions "A0 B1"
    When I send a "POST" request to "/component/dummy_components" with data:
      | componentPositions                                                            |
      | json_decode([{"componentGroup": "resource[component_group]", "after": "resource[A]"}]) |
    Then the response status code should be 201
    And I save the JSON node "componentPositions[0]" as the resource "N"
    And the ComponentGroup "component_group" should hold the positions "A0 N1 B2"

  @loginUser
  Scenario: A component created straight into a group with an anchor from another group is a 422 on the embedded anchor
    Given there is a ComponentGroup with the positions "A0 B1"
    And there is another ComponentGroup with the positions "X0"
    When I send a "POST" request to "/component/dummy_components" with data:
      | componentPositions                                                            |
      | json_decode([{"componentGroup": "resource[component_group]", "before": "resource[X]"}]) |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentPositions[0].before"
    And there should be 3 ComponentPosition resources

  @loginUser
  Scenario: A component created straight into a group with an anchor and a sortValue is a 422 on the embedded anchor
    Given there is a ComponentGroup with the positions "A0 B1"
    When I send a "POST" request to "/component/dummy_components" with data:
      | componentPositions                                                                            |
      | json_decode([{"componentGroup": "resource[component_group]", "after": "resource[A]", "sortValue": 1}]) |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "componentPositions[0].after"
    And there should be 2 ComponentPosition resources

  @loginUser
  Scenario: After an insert the group lists its positions by sortValue, including the new one
    Given there is a ComponentGroup with the positions "A0 B1"
    And there is a DummyComponent
    When I send a "POST" request to "/_/component_positions" with data:
      | componentGroup            | component                 | after       |
      | resource[component_group] | resource[dummy_component] | resource[A] |
    Then the response status code should be 201
    And I save the JSON node "@id" as the resource "N"
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the JSON node "componentPositions" should list the resources "A, N, B" in that order

  Scenario: Positions sharing a sortValue are listed by id
    Given there is a ComponentGroup with the positions "A5 B5 C5"
    When I send a "GET" request to the resource "component_group"
    Then the response status code should be 200
    And the JSON node "componentPositions" should list the resources "A, B, C" in that order

  @loginUser
  Scenario: A reorder changes the fewest positions
    Given there is a ComponentGroup with the positions "A0 B10 C20"
    When I send the order "B, A, C" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "B10 A11 C20"
    And Mercure updates should have been published for exactly the ComponentPositions "A"

  @loginUser
  Scenario: A reorder never gives a value below 0
    Given there is a ComponentGroup with the positions "A0 B1 C2"
    When I send the order "C, A, B" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "C2 A3 B4"
    And Mercure updates should have been published for exactly the ComponentPositions "A, B"

  @loginUser
  Scenario: A reorder removes ties
    Given there is a ComponentGroup with the positions "A5 B5 C5"
    When I send the order "A, B, C" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "A5 B6 C7"

  @loginUser
  Scenario: A position hidden by allowedComponents follows its current predecessor
    Given there is a ComponentGroup with the positions "A0 H10 B20"
    And the ComponentPosition "H" holds a RestrictedComponent
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    When I send the order "B, A" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the order response should list the sort values "B20 A21"
    And the ComponentGroup "component_group" should hold the positions "B20 A21 H22"

  @loginUser
  Scenario: A position added since the client loaded the group follows its current predecessor
    Given there is a ComponentGroup with the positions "A0 B10 N15"
    When I send the order "B, A" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the order response should list the sort values "B10 N15 A16"
    And the ComponentGroup "component_group" should hold the positions "B10 N15 A16"

  @loginUser
  Scenario: A position deleted since the client loaded the group is ignored
    Given there is a ComponentGroup with the positions "A0 B10 C20"
    And the ComponentPosition "C" has been deleted
    When I send the order "C, B, A" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the order response should list the sort values "B10 A11"
    And the ComponentGroup "component_group" should hold the positions "B10 A11"

  @loginUser
  Scenario Outline: An order naming a position outside the group, or one position twice, is a 422
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is another ComponentGroup with the positions "X0"
    When I send the order "<order>" for the ComponentGroup "component_group"
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "<path>"
    And the ComponentGroup "component_group" should hold the positions "A0 B10"
    Examples:
      | order        | path                  |
      | B, X, A      | componentPositions[1] |
      | B, A, B      | componentPositions[2] |
      | B, A, /nope  | componentPositions[2] |

  @loginUser
  Scenario: The reorder response is what a fresh read of the order returns
    Given there is a ComponentGroup with the positions "A0 B10 C20"
    When I send the order "C, A, B" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "component_group"
    And the order response should list the sort values "C20 A21 B22"
    And Mercure updates should have been published for exactly the ComponentPositions "A, B"
    And the order response should equal a fresh read of the order of the ComponentGroup "component_group"

  Scenario: Anyone who can read the group can read its order
    Given there is a ComponentGroup with the positions "A0 B10"
    When I send a "GET" request to the resource "component_group" and the postfix "/order"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "component_group"
    And the order response should list the sort values "A0 B10"

  @loginAdmin
  Scenario Outline: An admin reads the same order with or without the draft query
    Given there is a ComponentGroup with the positions "A0 B10"
    When I send a "GET" request to the resource "component_group" and the postfix "<postfix>"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "component_group"
    And the order response should list the sort values "A0 B10"
    Examples:
      | postfix        |
      | /order         |
      | /order?draft=1 |

  Scenario: The order of a group is addressed by its reference too
    Given there is a ComponentGroup with the positions "A0 B10"
    When I send a "GET" request to "/_/component_groups/collection/order"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "component_group"

  Scenario: The order of a group lists only the positions the group shows
    Given there is a ComponentGroup with the positions "A0 H10 B20"
    And the ComponentPosition "H" holds a RestrictedComponent
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    When I send a "GET" request to the resource "component_group" and the postfix "/order"
    Then the response status code should be 200
    And the order response should list the sort values "A0 B20"

  Scenario: The order of a group with no positions is empty
    Given there is a ComponentGroup with the positions ""
    When I send a "GET" request to the resource "component_group" and the postfix "/order"
    Then the response status code should be 200
    And the order response should list the sort values ""

  Scenario: The order of a group that does not exist is a 404
    When I send a "GET" request to "/_/component_groups/not-a-group/order"
    Then the response status code should be 404

  @loginUser
  Scenario: A reorder ignores the maximum number of components
    Given there is a ComponentGroup with the positions "A0 B1 C2"
    And the ComponentGroup "component_group" has the maxComponents 1
    When I send the order "C, B, A" for the ComponentGroup "component_group"
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "C2 B3 A4"

  @loginUser
  Scenario: A position moved into another group after an anchor is placed after it
    Given there is a ComponentGroup with the positions "A0 B1"
    And there is another ComponentGroup with the positions "X0 Y1"
    When I send a "PATCH" request to the resource "Y" with data:
      | componentGroup            | after       |
      | resource[component_group] | resource[A] |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 1
    And the ComponentGroup "component_group" should hold the positions "A0 Y1 B2"
    And the ComponentGroup "other_component_group" should hold the positions "X0"

  @loginUser
  Scenario: A position moved into another group with no anchor goes at the end
    Given there is a ComponentGroup with the positions "A0 B10"
    And there is another ComponentGroup with the positions "X0 Y1"
    When I send a "PATCH" request to the resource "X" with data:
      | componentGroup            |
      | resource[component_group] |
    Then the response status code should be 200
    And the JSON node "sortValue" should be equal to the number 11
    And the ComponentGroup "component_group" should hold the positions "A0 B10 X11"
    And the ComponentGroup "other_component_group" should hold the positions "Y1"

  @loginUser
  Scenario: A position moved within its group by an anchor is placed by the same rule as a reorder
    Given there is a ComponentGroup with the positions "A0 B1 C2"
    When I send a "PATCH" request to the resource "C" with data:
      | before      |
      | resource[A] |
    Then the response status code should be 200
    And the ComponentGroup "component_group" should hold the positions "C2 A3 B4"

  @loginUser
  Scenario: A position cannot be placed relative to itself
    Given there is a ComponentGroup with the positions "A0 B1"
    When I send a "PATCH" request to the resource "A" with data:
      | after       |
      | resource[A] |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to "after"
    And the ComponentGroup "component_group" should hold the positions "A0 B1"

  Scenario: An anonymous user cannot reorder a group
    Given there is a ComponentGroup with the positions "A0 B10"
    When I send the order "B, A" for the ComponentGroup "component_group"
    Then the response status code should be 401
    And the ComponentGroup "component_group" should hold the positions "A0 B10"

  @loginUser
  Scenario: A reorder of a group that does not exist is a 404
    When I send a "PUT" request to "/_/component_groups/not-a-group/order" with body:
    """
    {"componentPositions": []}
    """
    Then the response status code should be 404

  @loginUser
  Scenario: The order is the group's own IRI with /order appended, under the route import prefix
    Given the API routes are imported under the prefix "/_api"
    And there is a ComponentGroup with the positions "A0 B10"
    When I send a "GET" request to "/_api/_/component_groups/collection"
    Then the response status code should be 200
    And I save the JSON node "@id" as the resource "prefixed_group"
    And I add "Accept" header equal to "application/ld+json"
    When I send a "GET" request to the resource "prefixed_group" and the postfix "/order"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "prefixed_group"
    And the order response should list the sort values "A0 B10"
    And I add "Accept" header equal to "application/ld+json"
    And I add the logged in user's token to the request
    When I send the order "B, A" for the ComponentGroup "prefixed_group"
    Then the response status code should be 200
    And the order response should be the order of the ComponentGroup "prefixed_group"
    And the order response should list the sort values "B10 A11"
