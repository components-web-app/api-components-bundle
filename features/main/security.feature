Feature: Restrict loading of components and routes
  In order to secure specific pages in my application
  As an API user
  I can secure routes and components located within those routes

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A route fetched by its identifier is restricted based on the security policy
    Given there is a Route "/role-user-only" with a page
    When I send a "GET" request to the resource "route"
    Then the response status code should be 401

  @loginAdmin
  Scenario: I cannot get a collection of routes if I am not super admin
    Given there is a Route "/role-user-only" with a page
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 403

  @loginSuperAdmin
  Scenario: I can get a collection of routes as a super admin
    Given there is a Route "/role-user-only" with a page
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
