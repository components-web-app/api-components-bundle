Feature: Generating missing imagine variants ahead of requests
  In order to keep image processing out of visitors' requests after adding an imagine filter
  As a developer deploying a site
  I need a console command that generates every variant not stored yet, and does nothing for what is

  Background:
    Given the upload filestores are empty

  Scenario: The command generates each missing variant with its file info, and a second run generates nothing
    Given there is a DummyUploadableWithImagineFilters
    When I run the console command "silverback:api-components:imagine:generate" verbosely
    Then the console command should have exited with 0
    And the console command output should contain "  generated: thumbnail "
    And the console command output should contain "Generated: 3"
    And the console command output should contain "Already stored: 0"
    And there should be 1 file info rows for the imagine filter "thumbnail"
    And there should be 1 file info rows for the imagine filter "square_thumbnail"
    And there should be 1 file info rows for the imagine filter "webp_thumbnail"
    When I run the console command "silverback:api-components:imagine:generate"
    Then the console command should have exited with 0
    And the console command output should contain "Generated: 0"
    And the console command output should contain "Already stored: 3"
    And there should be 1 file info rows for the imagine filter "thumbnail"

  Scenario: A run stopped by its limit exits 3 and the next run finishes the variants still missing
    Given there is a DummyUploadableWithImagineFilters
    When I run the console command "silverback:api-components:imagine:generate --limit=1"
    Then the console command should have exited with 3
    And the console command output should contain "Generated: 1"
    And the console command output should contain "Stopped after 1 variant (--limit); variants are still missing, so run the command again."
    When I run the console command "silverback:api-components:imagine:generate"
    Then the console command should have exited with 0
    And the console command output should contain "Generated: 2"
    And the console command output should contain "Already stored: 1"

  Scenario: A dry run counts the missing variants and generates nothing
    Given there is a DummyUploadableWithImagineFilters
    When I run the console command "silverback:api-components:imagine:generate --dry-run"
    Then the console command should have exited with 0
    And the console command output should contain "To generate: 3"
    And there should be 0 file info rows for the imagine filter "thumbnail"
    When I run the console command "silverback:api-components:imagine:generate --dry-run"
    Then the console command output should contain "To generate: 3"

  Scenario: The filter option generates only that filter
    Given there is a DummyUploadableWithImagineFilters
    When I run the console command "silverback:api-components:imagine:generate --filter=thumbnail"
    Then the console command should have exited with 0
    And the console command output should contain "Generated: 1"
    And there should be 1 file info rows for the imagine filter "thumbnail"
    And there should be 0 file info rows for the imagine filter "square_thumbnail"

  Scenario: An imagine filter that is not configured is refused and nothing is generated
    Given there is a DummyUploadableWithImagineFilters
    When I run the console command "silverback:api-components:imagine:generate --filter=thumbnial"
    Then the console command should have exited with 2
    And the console command output should contain "is not a configured imagine filter."
    And there should be 0 file info rows for the imagine filter "thumbnail"
