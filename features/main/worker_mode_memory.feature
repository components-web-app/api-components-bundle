Feature: Nothing serialised in one request is kept for the next in worker mode
  In order that a long-running worker does not grow until it hits its memory limit
  As a FrankenPHP worker
  I need every service that collects objects during a request to let them go when services are reset

  Scenario: The skolem IRI map and the resource metadata map are empty after each request's services reset
    Given there is a PageData resource with a published component in a pageDataProperty position and the route path "/worker"
    Then requests to "/_/routes//worker,/_/resource_manifest//worker,/_/routes,/_/routes//worker" through one kernel leave nothing in the skolem IRI or resource metadata maps after a services reset
