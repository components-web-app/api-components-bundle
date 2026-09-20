Feature: Scheduled and draft route publication
  In order to launch a URL at a planned moment and take a URL offline without losing it
  As an API user
  I can give a Route a go-live date which gates public resolution, is inherited down the page
  hierarchy, and leaves admin access and editing untouched

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # A route's own go-live date

  Scenario: A route created without an explicit go-live date is live immediately
    Given there is a Route "/launch" with a page
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 200

  Scenario: An anonymous user cannot resolve a route before its go-live date
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 404

  Scenario: An anonymous user cannot resolve a route which has no go-live date
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 404

  Scenario: An anonymous user can resolve a route whose go-live date has passed
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2000-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 200

  @loginUser
  Scenario: An authenticated user without the publishable permission cannot resolve a scheduled route
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 404

  Scenario: A route restricted by route security still reports that authentication is required
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to "/_/routes//user-area/my-page"
    Then the response status code should be 401

  # Inheritance down the page hierarchy

  Scenario: A routed child under an unrouted parent page is live
    Given there is a PageData resource with the route path "/child-path" whose parent page has no route
    When I send a "GET" request to "/_/routes//child-path"
    Then the response status code should be 200

  Scenario: A routed child under a draft parent route is not live
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" has no go-live date
    When I send a "GET" request to "/_/routes//conference/programme"
    Then the response status code should be 404

  Scenario: A live child under a scheduled parent route inherits the parent go-live date
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//conference/programme"
    Then the response status code should be 404

  Scenario: A child with an earlier go-live date than its parent is gated by the parent
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    And the Route "/conference/programme" goes live at "2000-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//conference/programme"
    Then the response status code should be 404
    And the Route "/conference/programme" should not be live

  Scenario: A child with a later go-live date than its live parent is gated by its own date
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference/programme" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//conference"
    Then the response status code should be 200
    And the Route "/conference/programme" should not be live

  Scenario: A nested static page under a scheduled page data parent inherits the parent go-live date
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//conference/programme"
    Then the response status code should be 404

  Scenario: A child becomes live once its parent go-live date is moved into the past
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    And the Route "/conference" goes live at "2000-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//conference/programme"
    Then the response status code should be 200
    And the Route "/conference/programme" should be live

  @loginAdmin
  Scenario: A route generated under a scheduled parent inherits the parent go-live date
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "POST" request to "/_/routes/generate" with data:
      | pageData            |
      | resource[page_data] |
    Then the response status code should be 201
    And the Route "/conference/unnamed-page" should not be live

  # Admin access, editing and exposure

  @loginAdmin
  Scenario: An admin can resolve a scheduled route and see its go-live date
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 200
    And the JSON node "liveAt" should exist

  @loginAdmin
  Scenario: An admin can resolve a draft route
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 200

  @loginAdmin
  Scenario: An admin can schedule a route by patching its go-live date
    Given there is a Route "/launch" with a page
    When I send a "PATCH" request to the resource "route" with data:
      | liveAt                    |
      | 2999-01-01T00:00:00+00:00 |
    Then the response status code should be 200
    And the JSON node "liveAt" should exist
    And the Route "/launch" should not be live

  @loginAdmin
  Scenario: Patching a parent go-live date cascades to already persisted descendants
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    When I send a "PATCH" request to the resource "parent_route" with data:
      | liveAt                    |
      | 2999-01-01T00:00:00+00:00 |
    Then the response status code should be 200
    And the Route "/conference/programme" should not be live

  Scenario: The go-live date is not exposed to anonymous users
    Given there is a Route "/launch" with a page
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 200
    And the JSON node "liveAt" should not exist

  # Manifests

  Scenario: An anonymous user cannot get the manifest for a route before its go-live date
    Given there is a PageData resource with the route path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/resource_manifest//launch"
    Then the response status code should be 404

  Scenario: An anonymous user cannot get the manifest for a scheduled route by page data id
    Given there is a PageData resource with the route path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "page_data_manifest"
    Then the response status code should be 404

  @loginAdmin
  Scenario: An admin can get the manifest for a scheduled route
    Given there is a PageData resource with the route path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/resource_manifest//launch"
    Then the response status code should be 200
    And the manifest depth 0 root IRI should be "/_/routes//launch"

  # Collections

  Scenario: A scheduled route does not appear in the anonymous route collection
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: A draft route does not appear in the anonymous route collection
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  @loginSuperAdmin
  Scenario: A draft route appears in the admin route collection so it can be selected before launch
    Given there is a Route "/launch" with a page
    And the Route "/launch" has no go-live date
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should exist

  @loginSuperAdmin
  Scenario: A scheduled route appears in the admin route collection
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should exist

  Scenario: A page whose only route is scheduled does not appear in the anonymous page collection
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/pages"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  Scenario: A page whose only route is scheduled cannot be loaded anonymously
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 401

  # Components reachable only through a scheduled route

  Scenario: A component reachable only via a scheduled route is not publicly readable
    Given there is a component in a route with the path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 401

  Scenario: A component in both a scheduled and a live route is publicly readable
    Given there is a component in a route with the path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    And the resource "component_0" is in a route with the path "/any-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  @loginAdmin
  Scenario: A component reachable only via a scheduled route is readable by an admin
    Given there is a component in a route with the path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component in a scheduled PageData route is not publicly readable
    Given there is a component in a PageData route with the path "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 401

  # Redirects

  Scenario: A route redirecting to a scheduled route still reports the redirect path
    Given there is a Route "/old" which redirects to "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//old"
    Then the response status code should be 200
    And the JSON node redirectPath should be equal to the string "/launch"

  Scenario: A route redirecting to a scheduled route does not expose the scheduled page
    Given there is a Route "/old" which redirects to "/launch"
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//old"
    Then the response status code should be 200
    And the JSON node "page" should not exist

  Scenario: A scheduled route is not exposed in a live route's redirect tree
    Given there is a Route "/old" which redirects to "/launch"
    And the Route "/old" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "final_route" and the postfix "/redirects"
    Then the response status code should be 200
    And the JSON node "redirectedFrom[0].redirectedFrom[0]" should not exist

  @loginAdmin
  Scenario: A scheduled route is exposed in a live route's redirect tree for an admin
    Given there is a Route "/old" which redirects to "/launch"
    And the Route "/old" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "final_route" and the postfix "/redirects"
    Then the response status code should be 200
    And the JSON node "redirectedFrom[0].redirectedFrom[0].path" should be equal to the string "/old"

  # Children and path cascading remain admin operations

  @loginAdmin
  Scenario: An admin sees a scheduled child in the children tree
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference/programme" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "parent_route" and the postfix "/children"
    Then the response status code should be 200
    And the JSON node "children" should have 1 element

  @loginAdmin
  Scenario: PATCH a scheduled parent route path with cascadeChildPaths still cascades
    Given there is a PageData resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "PATCH" request to the resource "parent_route" with data:
      | path            | cascadeChildPaths |
      | /new-conference | true              |
    Then the response status code should be 200
    And the Route "/conference" should redirect to "/new-conference"
    And the Route "/conference/programme" should redirect to "/new-conference/programme"
