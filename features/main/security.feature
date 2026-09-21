Feature: Restrict loading of components and routes
  In order to secure specific pages in my application
  As an API user
  I can secure routes and components located within those routes

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A route retrieved by id is restricted based on the security policy
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to the resource "route"
    Then the response status code should be 401

  Scenario: A route retrieved by path is restricted based on the security policy
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to "/_/routes//user-area/my-page"
    Then the response status code should be 401

  Scenario: A route retrieved by path is allowed if not in config
    Given there is a Route "/my-page" with a page
    When I send a "GET" request to "/_/routes//my-page"
    Then the response status code should be 200

  Scenario: A collection of routes will not include pages what a user has no access to
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist

  @loginSuperAdmin
  Scenario: I can get a collection of routes as a super admin
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "member[0]" should exist

  Scenario: A component in a restricted route cannot be loaded by an anonymous user
    Given there is a component in a route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 401

  @loginUser
  Scenario: A component in a restricted route can be loaded by an authorised user
    Given there is a component in a route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component within a restricted route and a public route can be loaded by an anonymous user
    Given there is a component in a route with the path "/user-area/my-page"
    And the resource "component_0" is in a route with the path "/any-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component in a PageData resource which has a restricted route is also restricted
    Given there is a component in a PageData route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 401

  @loginUser
  Scenario: A component in a PageData resource which has a restricted route is also restricted
    Given there is a component in a PageData route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component in a PageData resource which is restricted by API Platform security metadata is also restricted
    Given there is a component in a RestrictedPageData route with the path null
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 401

  @loginAdmin
  Scenario: A component in a PageData resource which is restricted by API Platform security metadata is allowed by a user
    Given there is a component in a RestrictedPageData route with the path null
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component restricted in PageData but allowed in a static Page route is allowed
    Given there is a component in a RestrictedPageData route with the path null
    And the resource "component_0" is in a route with the path "/any-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A component forbidden in a static route but allowed within PageData is allowed
    Given there is a component in a route with the path "/user-area/my-page"
    And there is a component in a PageData route with the path "/any-path"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  # Un-routed routable page security
  Scenario: A component in a PageData resource which has a restricted route is also restricted
    Given there is a component in a PageData route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "page"
    Then the response status code should be 401

  Scenario: While a component within page data with a route should be accessible, the dynamic page should not be restricted
    Given there is a component in a PageData route with the path "/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200
    When I send a "GET" request to the resource "page_data"
    Then the response status code should be 200
    # we will need to be able to get the dynamic page as a resource when loading the data page
    When I send a "GET" request to the resource "page"
    Then the response status code should be 200

  Scenario: A routable resource is forbidden to be loaded without a route
    Given there is a Page
    When I send a "GET" request to the resource "page"
    Then the response status code should be 401

  Scenario: A routable resource with a public route can be loaded
    Given there is a Route "/my-page" with a page
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 200

  Scenario: A routable resource with a restricted route cannot be loaded by a public user
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to the resource "route_page"
    Then the response status code should be 401

  @loginAdmin
  Scenario: A routable resource without a route can be loaded by an admin
    Given there is a Page
    When I send a "GET" request to the resource "page"
    Then the response status code should be 200

  Scenario: Site settings can be loaded anonymously
    Given there is a SiteConfigParameter
    When I send a "GET" request to "/_/site_config_parameters"
    Then the response status code should be 200

  Scenario: Site settings cannot be deleted anonymously
    Given there is a SiteConfigParameter
    When I send a "DELETE" request to the resource "site_config_param"
    Then the response status code should be 401

  @loginAdmin
  Scenario: Site settings can be deleted by an admin
    Given there is a SiteConfigParameter
    When I send a "DELETE" request to the resource "site_config_param"
    Then the response status code should be 204

  Scenario: Site settings cannot be fetched individually anonymously
    Given there is a SiteConfigParameter
    When I send a "GET" request to the resource "site_config_param"
    Then the response status code should be 401

  @loginAdmin
  Scenario: Site settings can be fetched individually by an admin
    Given there is a SiteConfigParameter
    When I send a "GET" request to the resource "site_config_param"
    Then the response status code should be 200

  Scenario: Site settings cannot be created by anonymous users
    Given there is a SiteConfigParameter
    When I send a "POST" request to "/_/site_config_parameters" with data:
      | key     | value      |
      | bob     | uncle      |
    Then the response status code should be 401

  @loginAdmin
  Scenario: Site settings can be created by admin
    When I send a "POST" request to "/_/site_config_parameters" with data:
      | key     | value    |
      | bob     | uncle    |
    Then the response status code should be 201

  Scenario: Site settings cannot be updated by anonymous users
    Given there is a SiteConfigParameter
    When I send a "PUT" request to the resource "site_config_param" with data:
      | key     | value      |
      | new_key | new_value  |
    Then the response status code should be 401

  @loginAdmin
  Scenario: Site settings can be updated by admin
    Given there is a SiteConfigParameter
    When I send a "PUT" request to the resource "site_config_param" with data:
      | key     | value      |
      | new_key | new_value  |
    Then the response status code should be 200

  @loginAdmin
  Scenario: JWT cookie is cleared when the authenticated user is deleted from the database
    Given the logged in user has been deleted from the database
    When I send a "GET" request to "/me"
    Then the response status code should be 401
    And the response should have a "api_components" cookie with max age less than 2

  @loginAdmin
  Scenario: GET /me resolves the user by username even after the user entity is recreated with a new database ID
    Given the logged in user has been deleted from the database
    And the logged in user has been recreated with the same username
    When I send a "GET" request to "/me"
    Then the response status code should be 200
    And the JSON node "username" should be equal to "new_user"

  # Reachability through the page hierarchy: a resource is readable when a route that reaches it
  # exists and is live now, whether that route belongs to the resource or to a descendant of it.

  Scenario: A routeless parent PageData is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 200

  Scenario: The page template of a routeless parent PageData is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_template"
    Then the response status code should be 200

  Scenario: A component in the template of a routeless parent PageData is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: The manifest of a routeless parent PageData is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_manifest"
    Then the response status code should be 200

  Scenario: A routeless parent Page is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent Page with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_page"
    Then the response status code should be 200

  Scenario: A component in a routeless parent Page is readable anonymously when a routed child page declares it as its parent
    Given there is a routeless parent Page with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: A routeless ancestor two levels above a routed page is readable anonymously
    Given there is a chain of 2 routeless Pages ending in a routed Page with the path "/deep-child"
    When I send a "GET" request to the resource "chain_root"
    Then the response status code should be 200

  Scenario: A component in a routeless ancestor two levels above a routed page is readable anonymously
    Given there is a chain of 2 routeless Pages ending in a routed Page with the path "/deep-child"
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: A component in a routeless page which nothing routes to is not readable anonymously
    Given there is a routeless Page with a component and no routed descendant
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 401

  @loginAdmin
  Scenario: An admin can read a component in a routeless page which nothing routes to
    Given there is a routeless Page with a component and no routed descendant
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  @loginAdmin
  Scenario: An admin can read a component behind a route security route whose role they do not hold
    Given there is a component in a route with the path "/user-area/my-page"
    When I send a "GET" request to the resource "component_0"
    Then the response status code should be 200

  Scenario: A routeless page whose only child page has no route is not readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 401

  Scenario: A component in a routeless page with no routed descendant is not readable anonymously
    Given there is a routeless parent PageData with a component and an unrouted child Page
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 401

  @loginAdmin
  Scenario: An admin can read a component in a routeless page with no routed descendant
    Given there is a routeless parent PageData with a component and an unrouted child Page
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 200

  Scenario: A routeless parent is not readable anonymously when its only routed descendant has no go-live date
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    And the Route "/child-path" has no go-live date
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 401

  Scenario: A component of a routeless parent is not readable anonymously when its only routed descendant has no go-live date
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    And the Route "/child-path" has no go-live date
    When I send a "GET" request to the resource "parent_component"
    Then the response status code should be 401

  Scenario: A routeless parent is not readable anonymously when its only routed descendant is scheduled for the future
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    And the Route "/child-path" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 401

  @loginAdmin
  Scenario: An admin can read a routeless parent whose only routed descendant is scheduled for the future
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/child-path"
    And the Route "/child-path" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 200

  Scenario: A routeless parent whose only routed descendant is behind route security is not readable anonymously
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/user-area/child-path"
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 401

  @loginUser
  Scenario: A routeless parent whose only routed descendant is behind route security is readable by a permitted user
    Given there is a routeless parent PageData with a component and a routed child Page with the path "/user-area/child-path"
    When I send a "GET" request to the resource "parent_page_data"
    Then the response status code should be 200

  Scenario: A parent chain which loops back on itself does not prevent a response
    Given there are two routeless PageData resources which are each other's parent
    When I send a "GET" request to the resource "cycle_page_data"
    Then the response status code should be 401

  # Collections filter on a page's own route only: reachability is resolved by walking descendants,
  # which cannot be expressed in DQL. The item itself is readable, so this is an absence in a
  # listing rather than an access difference.

  Scenario: A routeless page reachable from a routed descendant is readable but is not listed in the anonymous page collection
    Given there is a routeless parent Page with a component and a routed child Page with the path "/child-path"
    When I send a "GET" request to the resource "parent_page"
    Then the response status code should be 200
    When I send a "GET" request to "/_/pages?order[reference]=asc"
    Then the response status code should be 200
    And the JSON node "member" should have 1 element
    And the JSON node "member[0].reference" should be equal to "routed child page"

  Scenario: A routeless page with no routed descendant does not appear in the anonymous page collection
    Given there is a routeless parent PageData with a component and an unrouted child Page
    When I send a "GET" request to "/_/pages"
    Then the response status code should be 200
    And the JSON node "member[0]" should not exist
