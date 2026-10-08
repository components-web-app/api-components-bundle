Feature: A page can be read as though it had a route
  In order to serve a CWA page from a route the application owns
  As an admin
  I can mark a page or page data as reachable without a route

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A routeless page marked reachable without a route is readable anonymously
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    When I send a "GET" request to the resource "orphan_page"
    Then the response status code should be 200

  Scenario: A component in a routeless page marked reachable without a route is readable anonymously
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: The manifest of a routeless page marked reachable without a route is readable anonymously
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    When I send a "GET" request to the resource "orphan_manifest"
    Then the response status code should be 200

  Scenario: A page marked reachable without a route is capped at the next go-live moment like a routed page
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    And there is a Route "/launch" with a page
    And the Route "/launch" goes live in 60 seconds
    When I send a "GET" request to the resource "orphan_page"
    Then the response status code should be 200
    And the response shared max age should be at most 60

  Scenario: Marking a page with a draft route reachable without a route does not make it readable anonymously
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    And the resource "route_page" is reachable without a route
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 401

  Scenario: Marking a page with a route behind route security reachable without a route does not make it readable anonymously
    Given there is a Route "/user-area/my-page" with a page
    And the resource "route_page" is reachable without a route
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 401

  Scenario: Marking a page with a scheduled route reachable without a route does not make it readable anonymously
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    And the resource "route_page" is reachable without a route
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 401

  Scenario: A routed page under a draft parent route is not readable anonymously
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" has no go-live date
    When I send a "GET" request to the resource "page"
    Then the response status code should be 401

  Scenario: A routeless page under a live parent route is not readable anonymously
    Given there is a routeless Page with a component nested within the route "/conference"
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 401

  Scenario: A routeless page marked reachable without a route under a live parent route is readable anonymously
    Given there is a routeless Page with a component nested within the route "/conference"
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 200

  Scenario: A page marked reachable without a route under a draft parent route is not readable anonymously
    Given there is a routeless Page with a component nested within the route "/conference"
    And the Route "/conference" has no go-live date
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 401

  Scenario: A component in a page marked reachable without a route under a draft parent route is not readable anonymously
    Given there is a routeless Page with a component nested within the route "/conference"
    And the Route "/conference" has no go-live date
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 401

  Scenario: A page marked reachable without a route under a scheduled parent route is not readable anonymously
    Given there is a routeless Page with a component nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 401

  Scenario: A page marked reachable without a route under a parent route behind route security is not readable anonymously
    Given there is a routeless Page with a component nested within the route "/user-area"
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 401

  @loginUser
  Scenario: A page marked reachable without a route under a parent route behind route security is readable by a permitted user
    Given there is a routeless Page with a component nested within the route "/user-area"
    And the resource "nested_page" is reachable without a route
    When I send a "GET" request to the resource "nested_page"
    Then the response status code should be 200

  Scenario: The routeless parent of a page marked reachable without a route is readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "child_page" is reachable without a route
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 200

  Scenario: A component in the routeless parent of a page marked reachable without a route is readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "child_page" is reachable without a route
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: Page data marked reachable without a route is readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "parent_page_data" is reachable without a route
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 200

  Scenario: The template of page data marked reachable without a route is readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "parent_page_data" is reachable without a route
    When I send a "GET" request to the resource "parent_template"
    Then the response status code should be 200

  Scenario: A component in the template of page data marked reachable without a route is readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "parent_page_data" is reachable without a route
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: Marking a template reachable without a route does not make its page data readable
    Given there is a routeless parent PageData with a component and an unrouted child Page
    And the resource "parent_template" is reachable without a route
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 401

  Scenario: A page marked reachable without a route is not listed in the anonymous page collection
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    When I send a "GET" request to "/_/pages"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: Whether a page is reachable without a route is not exposed anonymously
    Given there is a routeless Page with a component and no routed descendant
    And the resource "orphan_page" is reachable without a route
    When I send a "GET" request to the resource "orphan_page"
    Then the response status code should be 200
    And the JSON node "isReachableWithoutRoute" should not exist

  @loginAdmin
  Scenario: An admin can see that a page is not reachable without a route
    Given there is a routeless Page with a component and no routed descendant
    When I send a "GET" request to the resource "orphan_page"
    Then the response status code should be 200
    And the JSON node "isReachableWithoutRoute" should be false

  @loginAdmin
  Scenario: An admin can mark a page reachable without a route
    Given there is a valid Page with a ComponentGroup
    When I send a "PATCH" request to the resource "page" with body:
    """
    {
      "isReachableWithoutRoute": true
    }
    """
    Then the response status code should be 200
    And the JSON node "isReachableWithoutRoute" should be true

  @loginAdmin
  Scenario: An admin can mark page data reachable without a route
    Given there is a routeless parent PageData with a component and an unrouted child Page
    When I send a "PATCH" request to the resource "parent_page_data" with body:
    """
    {
      "isReachableWithoutRoute": true
    }
    """
    Then the response status code should be 200
    And the JSON node "isReachableWithoutRoute" should be true
