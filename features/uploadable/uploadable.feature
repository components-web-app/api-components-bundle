Feature: API Resources which can have files uploaded
  In order to create a resource with a file
  As an API user
  I need to be able to create a resource and upload a file

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # POST

  @loginUser
  @wip
  Scenario: I can create a new temporary media resource

  @loginUser
  @wip
  Scenario: I overwrite an existing temporary media resource instead of creating a new one

  # GET

  @loginUser
  @wip
  Scenario: I can get an image media resource with and without imagine filters configured

  # PUT

  @loginUser
  @wip
  Scenario: I can update a media resource

  @loginUser
  @wip
  Scenario: I can assign a media resource to a component

  # DELETE

  @loginUser
  @wip
  Scenario: I can update a media resource