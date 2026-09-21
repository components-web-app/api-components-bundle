Feature: Purging the front end's rendered HTML when a site-wide resource changes
  In order for a site-wide setting to take effect on pages the front end has already cached
  As an administrator
  I need a write to a configured resource class to purge the rendered HTML cache tag as well as
  its own resource IRIs, while a write to any other resource class purges only its own IRIs

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  @loginAdmin
  Scenario: Updating a site config parameter purges the rendered HTML tag as well as its own IRIs
    Given there is a SiteConfigParameter
    When I send a "PUT" request to the resource "site_config_param" with data:
      | key | value     |
      | key | new_value |
    Then the response status code should be 200
    And the resource "site_config_param" should be purged from the cache
    And the cache tag "cwa-html" should be purged

  @loginAdmin
  Scenario: Creating a site config parameter purges the rendered HTML tag
    When I send a "POST" request to "/_/site_config_parameters" with data:
      | key      | value |
      | siteName | CWA   |
    Then the response status code should be 201
    And the cache tag "cwa-html" should be purged

  @loginAdmin
  Scenario: Deleting a site config parameter purges the rendered HTML tag
    Given there is a SiteConfigParameter
    When I send a "DELETE" request to the resource "site_config_param"
    Then the response status code should be 204
    And the cache tag "cwa-html" should be purged

  @loginAdmin
  Scenario: A write to an unlisted resource class purges only its own IRIs
    Given there is a Layout
    And there is a ComponentGroup with 0 components
    When I send a "PATCH" request to the resource "component_group" with data:
      | layouts                             |
      | json_decode([ "resource[layout]" ]) |
    Then the response status code should be 200
    And the resource "layout" should be purged from the cache
    And the cache tag "cwa-html" should not be purged

  @loginAdmin
  Scenario: A single write sends the rendered HTML tag once, however many resources it invalidates
    Given there are 3 SiteConfigParameters
    When I send a "PUT" request to the resource "site_config_param_0" with data:
      | key   | value     |
      | key_0 | new_value |
    Then the response status code should be 200
    And the cache tag "cwa-html" should be purged 1 time
