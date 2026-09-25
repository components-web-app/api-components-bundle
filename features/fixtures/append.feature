Feature: Loading a scaffold into a database that already has one
  In order to add new scaffold content to a site editors are already working on
  As a developer
  I need an appended scaffold load to create only what is missing and leave everything else alone

  Scenario: Loading the same scaffold again changes nothing
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a page "home" using the layout "main" at the route "/" with a group "primary" holding the components "Hero, Text"
    And the scaffold has a template page "template" using the layout "main"
    And the scaffold has a page data titled "Conference" at the route "/conference" using the template "template"
    When the scaffold is loaded
    And the database contents are recorded
    And the scaffold is loaded again without purging
    Then the database contents should be unchanged
    And the last load summary should be "CWA scaffold: kept 2 pages, 1 page data, 1 layout, 2 groups"

  Scenario: A page added to the scaffold is created and an editor's change to existing content survives
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a page "home" using the layout "main" at the route "/" with a group "primary" holding the components "Hero"
    When the scaffold is loaded
    And an editor renames the component "Hero" to "Edited hero"
    And the database contents are recorded
    And the scaffold has a page "about" using the layout "main" at the route "/about" with a group "primary" holding the components "About text"
    And the scaffold is loaded again without purging
    Then the database should have gained only:
      | Page              | 1 |
      | Route             | 1 |
      | ComponentGroup    | 1 |
      | ComponentPosition | 1 |
      | Component         | 1 |
    And there should be 1 component labelled "Edited hero"
    And there should be 0 components labelled "Hero"
    And the last load summary should be "CWA scaffold: created 1 page, 1 route, 1 group, 1 component; kept 1 page, 1 layout, 2 groups"

  Scenario: A new page that uses an existing layout is attached to it
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a page "home" using the layout "main" at the route "/" with a group "primary" holding the components "Hero"
    When the scaffold is loaded
    And the scaffold has a page "about" using the layout "main" at the route "/about" with a group "primary" holding the components "About text"
    And the scaffold is loaded again without purging
    Then there should be 1 layout with the reference "main"
    And the page "about" should use the layout "main"

  Scenario: A new page whose route path belongs to another page is created without a route
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a page "about" using the layout "main" at the route "/about" with a group "primary" holding the components "About text"
    When the scaffold is loaded
    And the scaffold has a page "contact" using the layout "main" at the route "/about" with a group "primary" holding the components "Contact form"
    And the scaffold is loaded again without purging
    Then the page "contact" should have no route
    And the route "/about" should belong to the page "about"
    And the last load summary should contain "skipped 1 route (path in use)"

  Scenario: A group added to an existing page is created, and a component added to an existing group is not
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a page "home" using the layout "main" at the route "/" with a group "primary" holding the components "Hero"
    When the scaffold is loaded
    And the scaffold adds the components "Extra" to the group "primary" of the page "home"
    And the scaffold adds a group "sidebar" holding the components "Aside" to the page "home"
    And the scaffold is loaded again without purging
    Then the group "sidebar" of the page "home" should hold the components "Aside"
    And the group "primary" of the page "home" should hold the components "Hero"
    And there should be 0 components labelled "Extra"
    And the last load summary should be "CWA scaffold: created 1 group, 1 component; kept 1 page, 1 layout, 2 groups"

  Scenario: Page data with a generated route is kept rather than given a suffixed route
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a template page "template" using the layout "main"
    And the scaffold has a page data titled "Conference" at the route "/conference" using the template "template"
    And the scaffold has a page data titled "Programme" with a generated route under the page data "Conference" using the template "template"
    When the scaffold is loaded
    And the database contents are recorded
    And the scaffold is loaded again without purging
    Then the database contents should be unchanged
    And there should be 1 PageData titled "Programme"
    And there should be a route "/conference/programme"
    And the last load summary should be "CWA scaffold: kept 1 page, 2 page data, 1 layout, 1 group"

  Scenario: Page data with no route is created with its template and skipped once the template exists
    Given the scaffold has a layout "main" with a group "top" holding the components "Logo"
    And the scaffold has a template page "template" using the layout "main"
    And the scaffold has a page data titled "Draft" with no route using the template "template"
    When the scaffold is loaded
    Then there should be 1 PageData with no route
    When the database contents are recorded
    And the scaffold is loaded again without purging
    Then the database contents should be unchanged
    And the last load summary should contain "skipped 1 page data (unidentifiable)"

  Scenario: A generated scaffold loaded without purging duplicates and modifies nothing
    Given the site has a published component "live" with a draft "live-draft"
    And the site has an uploadable component with the file "image.png"
    And the site has a component "Tabs" whose own group "panels" holds a component "Panel"
    And the site has a navigation link to the page route "/about" with the event date "2027-01-01T10:00:00+00:00", the theme "dark" and the internal note "check"
    And the site has a PageDataWithComponent titled "Conference" whose component is a DummyComponent "Intro"
    And the site has a redirect from "/old-about" to "/about"
    When the site is generated as fixtures
    And the database contents are recorded
    And the generated fixtures are loaded without purging
    Then the database contents should be unchanged
    And the last load summary should not contain "created"

  Scenario: doctrine:fixtures:load prints the summary at default verbosity
    When I run the command "doctrine:fixtures:load --append --group=cwa_append"
    Then the command output should contain "CWA scaffold: created"
    When I run the command "doctrine:fixtures:load --append --group=cwa_append"
    Then the command output should contain "CWA scaffold: kept"
    And the command output should not contain "created"
