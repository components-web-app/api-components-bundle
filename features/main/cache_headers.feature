Feature: Cache-safety headers so shared caches can distinguish public from personalised responses
  In order for CDNs, reverse proxies and service workers to cache API responses safely
  As a consumer of the API
  I need authenticated responses on affected resource types marked non-cacheable, while public
  responses and unaffected resource types stay cacheable

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario Outline: Authenticated GETs of affected resource types are marked non-cacheable
    Given <setup>
    When <request>
    Then the response status code should be 200
    And the header "Cache-Control" should contain "private"
    And the header "Cache-Control" should contain "no-store"
    Examples:
      | setup                                                                                    | request                                                        |
      | there is a Route "/contact" with a page                                                  | I send a "GET" request to "/_/routes//contact"                 |
      | there is a PageData resource with the route path "/my-route"                             | I send a "GET" request to "/_/resource_manifest//my-route"     |
      | there is a ComponentGroup with 1 components                                              | I send a "GET" request to the resource "position_0"            |
      | there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00" | I send a "GET" request to the resource "publishable_published" |

  Scenario Outline: Anonymous GETs of affected resource types stay publicly cacheable
    Given <setup>
    When <request>
    Then the response status code should be 200
    And the header "Cache-Control" should contain "public"
    And the header "Cache-Control" should not contain "no-store"
    Examples:
      | setup                                                        | request                                                    |
      | there is a Route "/contact" with a page                      | I send a "GET" request to "/_/routes//contact"             |
      | there is a PageData resource with the route path "/my-route" | I send a "GET" request to "/_/resource_manifest//my-route" |
      | there is a ComponentGroup with 1 components                  | I send a "GET" request to the resource "position_0"        |

  @loginAdmin
  Scenario: An authenticated GET of an unaffected resource type stays publicly cacheable
    Given there is a Layout
    When I send a "GET" request to the resource "layout"
    Then the response status code should be 200
    And the header "Cache-Control" should contain "public"
    And the header "Cache-Control" should not contain "no-store"

  Scenario: An anonymous denial for a route which is not yet live is never stored by a shared cache
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live at "2999-01-01T00:00:00+00:00"
    When I send a "GET" request to "/_/routes//launch"
    Then the response status code should be 404
    And the header "Cache-Control" should contain "no-store"

  Scenario: The anonymous route collection cannot be cached beyond the next go-live moment
    Given there is a Route "/launch" with a page
    And the Route "/launch" goes live in 60 seconds
    When I send a "GET" request to "/_/routes"
    Then the response status code should be 200
    And the response shared max age should be at most 60
