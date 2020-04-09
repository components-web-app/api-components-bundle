Feature: Access to unpublished/draft resources should be configurable
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: A user creating a resource who does NOT have draft access retrieves a resource where a draft version available should have the PUBLISHED resource returned

  @createSchema
  Scenario: A user creating a resource who HAS draft access retrieves a resource where a draft version available should have the DRAFT resource returned

  @createSchema
  Scenario: A user creating a resource who does NOT have draft access should automatically create as published (if security access has been granted to do so)

  @createSchema
  Scenario: A user creating a resource who HAS draft access should create the resource as a draft version

  @createSchema
  Scenario: A user creating a resource who does NOT have draft access fetching a collection of a resource should NOT include draft resources

  @createSchema
  Scenario: A user creating a resource who HAS draft access fetching a collection of a resource should INCLUDE draft resources and should be in place of the published resources where available

  @createSchema
  Scenario: Access to the draft resources should be configurable with specified security roles

  @createSchema
  Scenario: Access to the draft resources should be configurable with security expressions

  @createSchema
  Scenario: A user who with an inherited role that has access to draft resources should also have them returned (Should use `isGranted` https://symfony.com/doc/current/security.html#hierarchical-roles)
