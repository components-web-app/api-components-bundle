Feature: A component placeholder object for dynamic pages
  In order to easily position components from page data objects in a page
  As an API user
  I must be able to create a component placeholder which is replaced by the real component

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  Scenario: A component placeholder is replaced
