Feature: API Platform Swagger Compatibility
  In order to use the swagger interactive documentation
  As an user
  I can visit the API documentation page

  Scenario: I can view the Swagger API Docs
    When I send a "GET" request to "/"
    Then the response status code should be 200
