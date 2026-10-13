Feature: Warming the API HTTP cache with the requests server-side rendering makes
  In order that a new API pod answers its first visitors from a warm cache
  As a deployment
  I need a command that requests every public route's resources with the module's headers, anonymously

  Background:
    Given I add "Accept" header equal to "application/ld+json"

  Scenario: Every request for a page data route with a dynamic position succeeds, the position carrying its depth's path
    Given there is a PageData resource with a published component in a pageDataProperty position and the route path "/warm-me"
    When I run the console command "silverback:api-components:warm-http-cache --concurrency=1"
    Then the console command should have exited with 0
    And the HTTP cache warm should have requested "/_/routes//warm-me"
    And the HTTP cache warm should have requested "/_/resource_manifest//warm-me"
    And the HTTP cache warm should have requested a URL matching "/\/_\/component_positions\//" with the path header "/warm-me"
    And the HTTP cache warm should have requested a URL matching "/\/component\/dummy_publishable_components\//" with the path header "/warm-me"
    And every HTTP cache warm request should have returned 200
    And the console command output should contain "Warmed 1 route with"
    And the console command output should contain "0 failed"

  Scenario: A route gated by its own or an ancestor's go-live date is not requested, and a redirect gets only its route lookup
    Given there is a Page resource with the route path "/conference/programme" nested within the route "/conference"
    And the Route "/conference" has no go-live date
    And there is a Route "/old" with a page and a redirect to "/new"
    When I run the console command "silverback:api-components:warm-http-cache"
    Then the console command should have exited with 0
    And the HTTP cache warm should not have requested a URL containing "conference"
    And the HTTP cache warm should have requested "/_/routes//old"
    And the HTTP cache warm should not have requested a URL containing "/_/resource_manifest//old"

  Scenario: A route only a logged-in user may read is not requested, and no request carries credentials
    Given there is a component in a route with the path "/user-area/secret"
    And there is a PageData resource with the route path "/public"
    When I run the console command "silverback:api-components:warm-http-cache"
    Then the console command should have exited with 0
    And the HTTP cache warm should have requested "/_/routes//public"
    And the HTTP cache warm should not have requested a URL containing "user-area"
    And no HTTP cache warm request should have carried credentials

  Scenario: An error response is reported and never fails the command
    Given there is a component in a route with the path "/public"
    And the HTTP cache warm gets a 500 response for URLs matching "/\/_\/pages\//"
    When I run the console command "silverback:api-components:warm-http-cache"
    Then the console command should have exited with 0
    And the console command output should contain "1 failed"
