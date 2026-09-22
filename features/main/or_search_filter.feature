Feature: A search filter combines its own clauses with OR and ANDs them against the rest of the query
  In order that supplying a filter parameter cannot bypass a publication gate or a draft exclusion
  As an anonymous API consumer
  A filtered collection must apply the query extensions' predicates as well as the filter's own

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A filtered anonymous route collection still excludes a scheduled route
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes?path=launch"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: A filtered anonymous route collection still excludes a draft route
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    When I send a "GET" request to "/_/routes?path=launch"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: A filtered anonymous route collection still excludes a route gated by its ancestor
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes?path=programme"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: A filtered anonymous route collection still returns a live route
    Given there is a Route "/launch" with a page
    When I send a "GET" request to "/_/routes?path=launch"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "1"
    And the JSON node "member[0].path" should be equal to the string "/launch"

  Scenario: The filter still matches across fields with OR
    Given there is a DummyOrSearchFilterable with field1 "alpha" and field2 "beta"
    And there is a DummyOrSearchFilterable with field1 "gamma" and field2 "alpha"
    When I send a "GET" request to "/dummy_or_search_filterables?field1=alpha&field2=alpha"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "2"

  Scenario: The filter still matches multiple values for one field with OR
    Given there is a DummyOrSearchFilterable with field1 "alpha" and field2 "beta"
    And there is a DummyOrSearchFilterable with field1 "gamma" and field2 "delta"
    And there is a DummyOrSearchFilterable with field1 "epsilon" and field2 "zeta"
    When I send a "GET" request to "/dummy_or_search_filterables?field1[]=alpha&field1[]=gamma"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "2"

  Scenario: A value that is invalid for the field's type is logged and the filter for that field is ignored
    Given there is a DummyOrSearchFilterable with field1 "alpha" and field2 "beta"
    And there is a DummyOrSearchFilterable with field1 "gamma" and field2 "delta"
    When I send a "GET" request to "/dummy_or_search_filterables?rank=not-an-integer"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "2"
    And an ignored filter value for the field "rank" should have been logged

  Scenario: An ignored invalid value leaves the other filter clauses applied
    Given there is a DummyOrSearchFilterable with field1 "alpha" and field2 "beta"
    And there is a DummyOrSearchFilterable with field1 "gamma" and field2 "delta"
    When I send a "GET" request to "/dummy_or_search_filterables?field1=alpha&rank=not-an-integer"
    Then the response status code should be 200
    And the JSON node "totalItems" should be equal to "1"
    And the JSON node "member[0].field1" should be equal to the string "alpha"

  @loginUser
  Scenario: A filtered collection does not expose a draft to a user without draft access
    Given there are 2 draft and published resources available
    When I send a "GET" request to "/component/dummy_publishable_components?reference=is"
    Then the response status code should be 200
    And the response should include the published resources only without the draftResources key
