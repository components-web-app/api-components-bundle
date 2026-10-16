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
    And the generated fixtures should contain "->draft("
    And the generated fixtures should not contain "afterRoutes("

  Scenario: A draft placed in its own position stays linked to its published component
    Given the site has a published component "live" with a draft "pending" placed after it in the same group
    When the site is generated as fixtures and reloaded
    Then the component "live" should be published and placed in a group
    And the component "pending" should be an unpublished draft of "live"
    And the component "pending" should be placed in a group
    And the generated fixtures should contain "->draft("
    And the command output should not contain "could not be reproduced"

  Scenario: A placed draft whose published component is in no position comes back with its published component
    Given the site has a published component "live" in no position with a draft "pending" placed in a group
    When the site is generated as fixtures and reloaded
    Then the component "pending" should be an unpublished draft of "live"
    And the component "pending" should be placed in a group
    And the component "live" should be published and in no position
    And the command output should not contain "could not be reproduced"

  Scenario: The IRI of a site page inside a component field links to the reloaded page
    Given the site has a page "blog" at the route "/blog" and a collection whose default query parameters hold the page's IRI under "parentPage"
    When the site is generated as fixtures
    Then the generated fixtures should contain "->linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog');"
    And the generated fixtures should contain "'pagination' => false, 'parentPage' => null"
    And the command output should not contain "could not be reproduced"
    When the database is purged and the generated fixtures are loaded
    Then the collection's default query parameters should hold the IRI of the page "blog" under "parentPage"

  Scenario: The IRI of something a scaffold cannot link to inside a field is reported
    Given the site has a collection whose default query parameters hold the IRI of page data with no route under "draft"
    When the site is generated as fixtures
    Then the command output should contain "could not be reproduced"
    And the command output should contain "Collection::$defaultQueryParameters["
    And the command output should contain "] holds the IRI"
    And the command output should contain "which is not a page, layout, routed page data or route of the generated site, so it is copied as it is."
    And the generated fixtures should not contain "linkIri("

  Scenario: A page with no route that is reachable without one keeps its flag
    Given the site has a page "landing" with no route that is reachable without one
    When the site is generated as fixtures and reloaded
    Then the page "landing" should be reachable without a route
    And the generated fixtures should contain "->reachableWithoutRoute()"

  Scenario: An uploaded file comes back with the same content
    Given the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures and reloaded
    Then the uploadable component should have a stored file with the same content as "image.png"

  Scenario: An upload stored under a PHP temp name is exported under the name its reload is stored under
    Given the site has an uploadable component whose file is stored as "components/phpGPCPOl" with the content of "image.png"
    When the site is generated as fixtures and reloaded
    Then the generated fixtures should contain "assets/phpgpcpol.png"
    And the exported assets should be "phpgpcpol.png"
    And the uploadable component should have a stored file with the same content as "image.png"
    When the site is generated as fixtures again
    Then the generated fixtures and their assets should be identical to the previous generation

  Scenario: A group owned by a component keeps its components
    Given the site has a component "Tabs" whose own group "panels" holds a component "Panel"
    When the site is generated as fixtures and reloaded
    Then the component "Tabs" should own a group "panels" holding the component "Panel"

  Scenario: A group owned by a component on a nested page keeps its components
    Given the site has a component "Tabs" whose own group "panels" holds a component "Panel" on the page "child" at the route "/parent/child" under the page "parent"
    When the site is generated as fixtures
    Then the generated fixtures should contain "->nested("
    When the database is purged and the generated fixtures are loaded
    Then the component "Tabs" should own a group "panels" holding the component "Panel"
    And the page "child" should have the parent page "parent"

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
    And the generated fixtures should contain "->linkRoute('route', routeName: 'about');"

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

  Scenario: A page with no route comes back without a route
    Given the site has a page "parent" with no route and a child page "child" at the route "/parent/child"
    When the site is generated as fixtures and reloaded
    Then the page "parent" should have no route
    And the route "/parent/child" should belong to the page "child"
    And there should be 1 Route resources

  Scenario: A page's uiComponent comes back as the full CwaPage component name
    Given the site has a page "legacy" with the uiComponent "PrimaryPageTemplate" at the route "/legacy"
    And the site has a page "seeded" with the uiComponent "CwaPagePrimaryPageTemplate" at the route "/seeded"
    When the site is generated as fixtures and reloaded
    Then the page "legacy" should have the uiComponent "CwaPagePrimaryPageTemplate"
    And the page "seeded" should have the uiComponent "CwaPagePrimaryPageTemplate"

  Scenario: A scaffold written in the documented style generates itself
    Given the site is loaded from the scaffold "scaffolds/SiteScaffold.php"
    When the site is generated as fixtures to the file "SiteScaffold.php"
    Then the generated fixtures should be identical to "scaffolds/SiteScaffold.php"
    And the exported assets should be identical to "scaffolds/assets"
    And the command output should not contain "could not be reproduced"

  Scenario: Long HTML with and without newlines reloads byte for byte
    Given the site has an HTML component for each long HTML sample
    When the site is generated as fixtures
    Then no line of the generated fixtures should be longer than 5100 characters
    And the generated fixtures should contain "<<<'HTML'"
    When the database is purged and the generated fixtures are loaded
    Then every HTML component should hold exactly its sample

  Scenario: A scheduled publishedAt comes back exact and a past one is published at load time
    Given the site has a published component "past" published at "2020-01-01T00:00:00+00:00"
    And the site has a published component "soon" published at "2099-01-15T09:00:00+00:00"
    When the site is generated as fixtures
    Then the generated fixtures should contain "$cwa->publish($pastDummyPublishableComponent);"
    And the generated fixtures should contain "$cwa->publish($soonDummyPublishableComponent, at: new \DateTimeImmutable('2099-01-15T09:00:00+00:00'));"
    And the generated fixtures should not contain "2020-01-01"
    And the generated fixtures should not contain "setPublishedAt"
    When the database is purged and the generated fixtures are loaded
    Then the component "soon" should be published at "2099-01-15T09:00:00+00:00"
    And the component "past" should have been published when it was loaded

  Scenario: With --keep-timestamps every publishedAt and liveAt comes back exact
    Given the site has a published component "past" published at "2020-01-01T00:00:00+00:00"
    And the site has a page "about" with the meta description "About us" at the route "/about" going live at "2001-02-03T04:05:06+00:00"
    When the site is generated as fixtures with the option "--keep-timestamps" and reloaded
    Then the component "past" should be published at "2020-01-01T00:00:00+00:00"
    And the route "/about" should go live at "2001-02-03T04:05:06+00:00"

  Scenario: A component in a layout group and in a nested page's group is one row in two positions
    Given the site has a navigation link "Contact us" in the layout group "header" and in the group "primary" of the page "team" nested under the page "about"
    When the site is generated as fixtures
    Then the generated fixtures should contain "private DummyNavigationLink $contactUsDummyNavigationLink;"
    And the generated fixtures should contain "private function teamPage(CwaFixtureBuilder $cwa): void"
    When the database is purged and the generated fixtures are loaded
    Then there should be 1 navigation link labelled "Contact us" in 2 positions

  Scenario: A grandchild page comes back under its parent
    Given the site has a page "grandchild" at the route "/parent/child/grandchild" under the page "child" at the route "/parent/child" under the page "parent" at the route "/parent"
    When the site is generated as fixtures
    Then the generated fixtures should contain "->nested($this->childPage(...));"
    And the generated fixtures should contain "->nested($this->grandchildPage(...));"
    And the generated fixtures should contain "private function grandchildPage(CwaFixtureBuilder $cwa): void"
    When the database is purged and the generated fixtures are loaded
    Then the page "grandchild" should have the parent page "child"
    And the page "child" should have the parent page "parent"
    And the route "/parent/child/grandchild" should belong to the page "grandchild"

  Scenario: A navigation link to a redirect route and to a page with a derived route name keeps its route
    Given the site has a page "about" at the route "/about" with a derived route name
    And the site has a redirect from "/old-about" to "/about"
    And the site has a navigation link "Old about" to the route "/old-about"
    And the site has a navigation link "About" to the route "/about"
    When the site is generated as fixtures
    Then the generated fixtures should contain "->linkRoute('route', routeName: 'old-about');"
    And the generated fixtures should contain "->linkRoute('route', routeName: 'about');"
    And the generated fixtures should not contain "route: '/about', routeName:"
    And the generated fixtures should not contain "name: 'old-about'"
    When the database is purged and the generated fixtures are loaded
    Then the navigation link "Old about" should point at the route "/old-about"
    And the navigation link "About" should point at the route "/about"

  Scenario: A component related to another component keeps the relation
    Given the site has a component "Card" related to a component "Teaser" in the group "primary" of the page "home"
    And the site has a component "Banner" in the layout "main" related to a component "Promo" on the page "home"
    When the site is generated as fixtures and reloaded
    Then the component "Card" should be related to the component "Teaser"
    And the component "Banner" should be related to the component "Promo"
    And there should be 1 component labelled "Teaser"
    And there should be 1 component labelled "Promo"

  Scenario: Page data keeps a route association
    Given the site has a page "about" at the route "/about" with a derived route name
    And the site has a PageDataWithRoute titled "Event" at the route "/event" whose related route is "/about"
    When the site is generated as fixtures
    Then the generated fixtures should contain "->linkRoute('relatedRoute', routeName: 'about')"
    When the database is purged and the generated fixtures are loaded
    Then the PageDataWithRoute "Event" should have the related route "/about"

  Scenario: A draft-owned group is reported as not reproduced
    Given the site has a published component "live" with a draft "live-draft" that owns a group "panels"
    When the site is generated as fixtures
    Then the command output should contain "could not be reproduced"
    And the command output should contain "panels_"
    And the generated fixtures should not contain "->group('panels')"
