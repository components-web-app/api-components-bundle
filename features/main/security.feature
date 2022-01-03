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
    And the JSON node "hydra:member[0]" should not exist

  @loginSuperAdmin
  Scenario: I can get a collection of routes as a super admin
    Given there is a Route "/user-area/my-page" with a page
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the JSON node "hydra:member[0]" should exist

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

  Scenario: A component in a PageData resource which has a restricted route is also restricted
    Given there is a component in a PageData route with the path "/my-page"
    When I send a "GET" request to the resource "page"
    Then the response status code should be 200

  Scenario: A routable resource is forbidden to be loaded
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

  Scenario: A routable resource cannot be loaded by public users without a route
    Given there is a Page
    When I send a "GET" request to the resource "page"
    Then the response status code should be 401

  @loginAdmin
  Scenario: A routable resource can be loaded by an admin
    Given there is a Page
    When I send a "GET" request to the resource "page"
    Then the response status code should be 200
