Feature: Register process via a form
  In order to register a new user
  As an application / client
  I must be able to create a register form and login

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginUser
  @wip
  Scenario: I can change my password
    Given there is a "change_password" form

  @loginUser
  @wip
  Scenario: I cannot change my password if my old password is incorrect
    Given there is a "change_password" form

  @loginUser
  @wip
  Scenario: I cannot change my password if my passwords do not match
    Given there is a "change_password" form

  @loginUser
  @wip
  Scenario: As a user, I cannot change another user's password as a user
    Given there is a "change_password" form

  @loginAdmin
  @wip
  Scenario: As an admin, I cannot change another user's password
    Given there is a "change_password" form

  @loginSuperAdmin
  @wip
  Scenario: As a Super Admin I can change another user's password
    Given there is a "change_password" form
