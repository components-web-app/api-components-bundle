Feature: explicitAllowOnly component types are flagged in the API documentation
  In order for the admin UI to hide component types that may only go where explicitly allowed
  As the front-end module (which reads the Hydra API docs, not per-instance _metadata)
  The API docs must expose a per-type `explicitAllowOnly` flag on the component's supportedClass entry

  Scenario: The API docs flag explicitAllowOnly component types on their supportedClass entry
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When I send a "GET" request to "/docs"
    Then the response status code should be 200
    And the API docs supportedClass "RestrictedComponent" should have explicitAllowOnly "true"
    And the API docs supportedClass "DummyComponent" should have explicitAllowOnly "false"
