Feature: A public Mercure update carries only what an anonymous visitor may read
  In order to keep drafts and admin-only fields off the topics every visitor receives
  As an application developer
  I need the public update to be an anonymous GET of the resource and the writer's view to reach admins privately afterwards

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: An admin edit to a position whose component has a draft publishes the published component publicly and the draft privately
    Given there is a routed Page "/mercure" whose group holds a published DummyPublishableComponent with a draft
    And only Mercure updates published from now are recorded
    When I send a "PATCH" request to the resource "position" with data:
      | componentGroup                  |
      | resource[other_component_group] |
    Then the response status code should be 200
    And the response should carry no cache tags
    And the header "cache-control" should contain "private"
    And the response should not have a "__Secure-mercure_access_token" cookie
    And the Mercure updates for the resource "position" should be published publicly and then privately
    And the public Mercure update for the resource "position" should have the JSON node "component" equal to the IRI of the resource "published_component"
    And the private Mercure update for the resource "position" should have the JSON node "component" equal to the IRI of the resource "draft_component"
    And no public Mercure update should contain the IRI of the resource "draft_component"
    And the public Mercure update for the resource "position" should equal an anonymous GET of it

  @loginAdmin
  Scenario: An admin editing a page that no route reaches publishes only a delete publicly
    Given there is a valid Page that no route reaches
    And only Mercure updates published from now are recorded
    When I send a "PATCH" request to the resource "page" with body:
      """
      {"title": "A secret title"}
      """
    Then the response status code should be 200
    And the Mercure updates for the resource "page" should be published publicly and then privately
    And the public Mercure update for the resource "page" should be a delete
    And the private Mercure update for the resource "page" should have the JSON node "title" equal to "A secret title"
    And no public Mercure update should contain "A secret title"

  @loginAdmin
  Scenario: An admin scheduling a route publishes only a delete publicly
    Given there is a Route "/scheduled" with a page
    And only Mercure updates published from now are recorded
    When I send a "PATCH" request to the resource "route" with body:
      """
      {"liveAt": "2999-01-01T00:00:00+00:00"}
      """
    Then the response status code should be 200
    And the Mercure updates for the resource "route" should be published publicly and then privately
    And the public Mercure update for the resource "route" should be a delete
    And the private Mercure update for the resource "route" should have the JSON node "liveAt" equal to "2999-01-01T00:00:00+00:00"
    And no public Mercure update should contain "2999"

  Scenario: A published component moved to a future publication date is deleted from its public topic
    Given there is a routed Page "/mercure" whose group holds a published DummyPublishableComponent
    And only Mercure updates published from now are recorded
    When the publication date of the resource "published_component" is moved to "2999-01-01 00:00:00" in the database
    Then the public Mercure update for the resource "published_component" should be a delete
    And the private Mercure update for the resource "published_component" should have the JSON node "publishedAt" equal to "2999-01-01T00:00:00+00:00"

  @loginAdmin
  Scenario: A draft edit is published only privately on the draft topic
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    And only Mercure updates published from now are recorded
    When I send a "PATCH" request to the resource "publishable_draft" with body:
      """
      {"reference": "edited draft"}
      """
    Then the response status code should be 200
    And the Mercure update for the resource "publishable_draft" should be one private update on its draft topic
    And no public Mercure update should contain "edited draft"

  @loginAdmin
  Scenario: A removed resource is published as a public delete and nothing else
    Given there is a Page
    And only Mercure updates published from now are recorded
    When I send a "DELETE" request to the resource "page"
    Then the response status code should be 204
    And the public Mercure update for the resource "page" should be a delete
    And there should be no private Mercure update for the resource "page"
