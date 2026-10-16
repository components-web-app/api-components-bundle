Feature: A fixture load writes content and originals only
  In order to load a site with many images in a pod with little memory
  As a developer
  I need a fixture load to propagate no update, so no image variant is generated, and to leave the variants to imagine:generate

  Scenario: Loading fixtures publishes no Mercure update and generates no imagine variant
    When I run the command "doctrine:fixtures:load --append --group=cwa_image"
    Then no Mercure update should have been published
    And there should be 0 file info rows for the imagine filter "thumbnail"
    And there should be 0 file info rows for original files
    And the command output should contain "The configured HTTP cache purger cannot flush the whole cache, so nothing was flushed"
    And the command output should contain "Run silverback:api-components:imagine:generate to generate the image variants"
