Feature: Super Admin users have more information on users
  In order to fully manage users
  As a Super Admin
  I should have access to properties not available to a normal user

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginSuperAdmin
  Scenario:
    Given there is a user with the username "my_user" password "password" and role "ROLE_ADMIN"
    When I send a "GET" request to the resource "user"
    Then the response status code should be 200
    And the JSON should be valid according to the schema file "user_super_admin.schema.json"
