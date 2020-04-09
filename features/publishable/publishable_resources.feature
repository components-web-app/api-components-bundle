Feature: Resources can implement `PublishableInterface` and therefore be published or unpublished/draft
  In order to make changes to a component or where it is positioned in the website
  As a user with configured roles (by default ROLE_ADMIN)
  I must be able to create components as drafts and publish them when ready

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Creating a new resource implementing the `PublishableInterface` should default to being unpublished (draft / 'published' property should be false)

  @createSchema
  Scenario: Modifying a published resource should create a new draft to modify in the database if it does not exist

  @createSchema
  Scenario: Modifying a published resource should modify the existing draft version if it exists

  @createSchema
  Scenario: If the querystring published=true exists, when fetching an individual resource, the currently published resource should be returned

  @createSchema
  Scenario: If the querystring published=true exists and there is no published version of the resource, when fetching an individual resource, we should receive the appropriate 404 response

  @createSchema
  Scenario: When fetching a collection of resources, draft resources should appear in place of published resources

  @createSchema
  Scenario: If the querystring published=true exists, when fetching a collection of resources, only published resources should be returned

  @createSchema
  Scenario: A published resource which has a draft version with a 'publish date' set should be retrieved with a cache expires header for when the new resources will be available

  @createSchema
  Scenario: A draft resource with a 'publish date' set should be converted to the published entity when the published entity is being retrieved

  @createSchema
  Scenario: A if a draft resource has a published version already and the 'published' property is set to 'true' the draft should be merged with the published version
