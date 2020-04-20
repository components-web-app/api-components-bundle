Feature: Access to unpublished/draft resources should be configurable
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

  # GET collection
  @createSchema
  @login
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, it should include the draft resources instead of the published ones.
    When I get a collection of published resources with draft resources available
    Then it should include the draft resources instead of the published ones

  @createSchema
  @login
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, and published=true query filter, it should include the published resources only.
    When I get a collection of published resources with draft resources available and published=true query filter
    Then it should include the published resources only

  @createSchema
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, it should include the published resources only.

  @createSchema
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, and published=true query filter, it should not include the draft resources.

  # POST
  @createSchema
  Scenario: As a user with draft access, when I create a resource, I should have the draft resource returned.

  @createSchema
  Scenario: As a user with draft access, when I create a resource with an active publication date, I should have the published resource returned.

  @createSchema
  Scenario: As a user with draft access, when I create a resource with a future publication date, I should have the draft resource returned.

  @createSchema
  Scenario: As a user with no draft access, when I create a resource, I should have the published resource returned, and the publication date is automatically set.

  # GET item
  @createSchema
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, I should have the draft resource returned.
  # todo Check cache-expiry header

  @createSchema
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, and published=true query filter, I should have the published resource returned.
  # todo Check cache-expiry header

  @createSchema
  Scenario: As a user with draft access, when I get a draft resource with published=true query filter, I should have a 404 error.
  # todo Check cache-expiry header

  @createSchema
  Scenario: As any user, when I get a resource with a past publication date, and a draft resource available with an active publication date, the draft resource replaces the published one, and the old one is removed.
  # todo Check cache-expiry header

  @createSchema
  Scenario: As a user with no draft access, when I get a published resource with a draft resource available, I should have the published resource returned.
  # todo Check cache-expiry header

  @createSchema
  Scenario: As a user with no draft access, when I get a published resource with a draft resource available, and published=false query filter, I should have the published resource returned anyway.
  # todo Check cache-expiry header

  # PUT
  @createSchema
  Scenario: As a user with draft access, when I update a published resource, it should create and return a draft resource.

  @createSchema
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, it should update and return the draft resource.

  @createSchema
  Scenario: As a user with draft access, when I update a published resource with a publication date in the past (or now), it should update and return the published resource.
  # Use Scenario Outline to check "in the past" and "now"

  @createSchema
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the past (or now), it should update and return the published resource, and remove the draft resource.
  # Use Scenario Outline to check "in the past" and "now"

  @createSchema
  Scenario: As a user with draft access, when I update a published resource with a publication date in the future, it should create and return a draft resource.

  @createSchema
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the future, it should update and return the draft resource.

  @createSchema
  Scenario: As a user with no draft access, when I update a published resource, it should update and return the published resource.

  @createSchema
  Scenario: As a user with no draft access, I cannot update a draft resource.

  @createSchema
  Scenario: As a user with no draft access, when I update the publication date of a published resource, the publication date is not changed.

  # DELETE
  @createSchema
  Scenario: As any user, when I delete a published resource with a draft resource available, it should delete the published resource and keep the draft.

  @createSchema
  Scenario: As a user with draft access, I can delete a draft resource.

  @createSchema
  Scenario: As a user with no draft access, I cannot delete a draft resource.