Feature: A site generated as fixtures reloads as the same site
  In order to rebuild a database from its scaffold without losing what editors added
  As a developer
  I need the scaffold that generate-fixtures writes to load and give back the same content

  Scenario: A group with allowed components and positions generates valid PHP and keeps its allowed components
    Given the site has a layout group and a page group that each allow and hold a DummyComponent
    When the site is generated as fixtures
    Then the generated fixtures should be valid PHP
    When the database is purged and the generated fixtures are loaded
    Then the group "top" of the layout "main" should allow only "DummyComponent" and hold 1 component
    And the group "primary" of the page "home" should allow only "DummyComponent" and hold 1 component

  Scenario: Published components stay published and a draft stays linked to its published component
    Given the site has a published component "live" with a draft "live-draft"
    When the site is generated as fixtures and reloaded
    Then the component "live" should be published and placed in a group
    And the component "live-draft" should be an unpublished draft of "live"

  Scenario: An uploaded file comes back with the same content
    Given the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures and reloaded
    Then the uploadable component should have a stored file with the same content as "image.png"

  Scenario: A group owned by a component keeps its components
    Given the site has a component "Tabs" whose own group "panels" holds a component "Panel"
    When the site is generated as fixtures and reloaded
    Then the component "Tabs" should own a group "panels" holding the component "Panel"

  Scenario: A group shared by a layout and a page through a location reference stays one group
    Given the site has a group "nav" shared by the layout "main" and the page "home" through the location reference "site"
    When the site is generated as fixtures and reloaded
    Then there should be 1 component group with the reference "nav_site"
    And the component group "nav_site" should belong to the layout "main" and the page "home"

  Scenario: A component keeps its route, dates, inherited and non-public properties
    Given the site has a navigation link to the page route "/about" with the event date "2027-01-01T10:00:00+00:00", the theme "dark" and the internal note "check"
    When the site is generated as fixtures and reloaded
    Then the navigation link should point at the route "/about"
    And the navigation link should have the event date "2027-01-01T10:00:00+00:00", the theme "dark" and the internal note "check"

  Scenario: Page and route fields survive, including scheduled and draft routes and redirects
    Given the site has a page "about" with the meta description "About us" at the route "/about" going live at "2999-01-01T00:00:00+00:00"
    And the site has a page "hidden" at the route "/hidden" with no go-live date
    And the site has a redirect from "/old-about" to "/about"
    When the site is generated as fixtures and reloaded
    Then the page "about" should have the meta description "About us"
    And the route "/about" should go live at "2999-01-01T00:00:00+00:00"
    And the route "/hidden" should have no go-live date
    And the Route "/old-about" should redirect to "/about"

  Scenario: A page data property holding a component keeps it
    Given the site has a PageDataWithComponent titled "Conference" whose component is a DummyComponent "Intro"
    When the site is generated as fixtures and reloaded
    Then the PageDataWithComponent "Conference" should hold the component "Intro"

  Scenario: Page data with no route comes back without a route
    Given the site has a PageData titled "Draft" with no route
    When the site is generated as fixtures and reloaded
    Then there should be 1 PageData with no route

  Scenario: Two page data with the same title both come back
    Given the site has a PageData titled "Same" at the route "/same-one"
    And the site has a PageData titled "Same" at the route "/same-two"
    When the site is generated as fixtures and reloaded
    Then there should be 2 PageData titled "Same"

  Scenario: The generated class is named after the output file
    Given the site has a layout group and a page group that each allow and hold a DummyComponent
    When the site is generated as fixtures to the file "SnapshotFixtures.php" and reloaded
    Then the group "primary" of the page "home" should allow only "DummyComponent" and hold 1 component
