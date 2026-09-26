Feature: Reporting and deleting orphaned files
  In order to find stored files nothing references any more, and uploads whose file has gone
  As an administrator
  I need to request a scan, fetch the report it stored, and delete the orphaned files I choose

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    And the upload filestores are empty

  @loginAdmin
  Scenario: A stored file no row references is reported on every uploadable filesystem that holds it, and the imagine cache is not scanned
    Given there is a stored file "orphan-0a1b2c3d.png" older than the minimum age
    And there is a stored file "components/nested/notes-1234abcd.txt" older than the minimum age
    And there is a stored file "cache/thumbnail/orphan-0a1b2c3d.png" older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/scan"
    Then the response status code should be 202
    When I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "generatedAt" should be now
    And the JSON node "orphanedFiles" should list exactly the files "local:components/nested/notes-1234abcd.txt, local:orphan-0a1b2c3d.png, public_url_local:components/nested/notes-1234abcd.txt, public_url_local:orphan-0a1b2c3d.png"
    And the JSON node "missingFiles" should have 0 elements
    And the JSON node "unknownFiles" should have 0 elements

  @loginAdmin
  Scenario: A file a row references is not reported, including one only a draft references
    Given there is a DummyUploadable with the file "image.png" saved as "referenced"
    And the stored file of the resource "referenced" is older than the minimum age
    And there is a draft DummyUploadableAndPublishable
    And the stored file of the resource "dummy_uploadable_draft" is older than the minimum age
    And there is a stored file "orphan-0a1b2c3d.png" older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/scan"
    And I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "orphanedFiles" should list exactly the files "local:orphan-0a1b2c3d.png, public_url_local:orphan-0a1b2c3d.png"
    And the JSON node "missingFiles" should have 0 elements

  @loginAdmin
  Scenario: An unreferenced file the bundle did not name and never served is reported as unknown, not orphaned
    Given there is a stored file "logo.png" older than the minimum age
    And there is a stored file "served-before-tokens.png" older than the minimum age
    And the stored file "served-before-tokens.png" has file info
    And there is a stored file "tokenised-0a1b2c3d.png" older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/scan"
    And I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "orphanedFiles" should list exactly the files "local:served-before-tokens.png, local:tokenised-0a1b2c3d.png, public_url_local:served-before-tokens.png, public_url_local:tokenised-0a1b2c3d.png"
    And the JSON node "unknownFiles" should list exactly the files "local:logo.png, public_url_local:logo.png"

  @loginAdmin
  Scenario: An unknown file is never deleted: all leaves it in place and naming it is rejected
    Given there is a stored file "logo.png" older than the minimum age
    And there is a stored file "tokenised-0a1b2c3d.png" older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 200
    And the JSON node "deleted" should list exactly the files "local:tokenised-0a1b2c3d.png, public_url_local:tokenised-0a1b2c3d.png"
    And the stored file "logo.png" should exist
    And the stored file "tokenised-0a1b2c3d.png" should not exist
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When I request the deletion of the orphaned files "logo.png"
    Then the response status code should be 200
    And the JSON node "deleted" should have 0 elements
    And the JSON node "rejected[0].path" should be equal to "logo.png"
    And the JSON node "rejected[0].reason" should be equal to "unknown"
    And the stored file "logo.png" should exist

  @loginAdmin
  Scenario: A file younger than the minimum age is not reported, because its row may not be flushed yet
    Given there is a stored file "uploading.png" written just now
    When I send a "POST" request to "/_/orphaned_files/scan"
    And I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "orphanedFiles" should have 0 elements

  @loginAdmin
  Scenario: A row whose stored file is missing is reported as missing and never as orphaned
    Given there is a DummyUploadable with the file "image.png" saved as "broken"
    And the stored file for the resource "broken" has been removed from its filestore
    When I send a "POST" request to "/_/orphaned_files/scan"
    And I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "orphanedFiles" should have 0 elements
    And the JSON node "missingFiles" should have 1 element
    And the JSON node "missingFiles[0].resource" should be equal to the IRI of the resource "broken"
    And the JSON node "missingFiles[0].adapter" should be equal to "local"
    And the JSON node "missingFiles[0].path" should be equal to the stored path of the resource "broken"

  @loginAdmin
  Scenario: The report is 404 until a scan has stored one
    When I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 404

  @loginAdmin
  Scenario: The report is never stored by a shared cache, because a later scan is not a write that could purge it
    Given I send a "POST" request to "/_/orphaned_files/scan"
    When I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the header "Cache-Control" should contain "private"
    And the header "Cache-Control" should contain "no-store"

  @loginUser
  Scenario: A user without the administrator role cannot request a scan
    When I send a "POST" request to "/_/orphaned_files/scan"
    Then the response status code should be 403
    And no orphaned files report should have been stored

  @loginUser
  Scenario: A user without the administrator role cannot fetch the report
    Given an orphaned files report has been stored
    When I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 403

  @loginUser
  Scenario: A user without the administrator role cannot delete orphaned files
    Given there is a stored file "orphan-0a1b2c3d.png" older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 403
    And the stored file "orphan-0a1b2c3d.png" should exist

  Scenario: An anonymous request for the report is refused by the operation's own security
    Given an orphaned files report has been stored
    When I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 401

  @loginAdmin
  Scenario: Deleting selected paths deletes only those, and the stored report no longer lists them
    Given there is a stored file "first-00000001.png" older than the minimum age
    And there is a stored file "second-00000002.png" older than the minimum age
    When I request the deletion of the orphaned files "first-00000001.png"
    Then the response status code should be 200
    And the JSON node "deleted" should list exactly the files "local:first-00000001.png, public_url_local:first-00000001.png"
    And the JSON node "rejected" should have 0 elements
    And the stored file "first-00000001.png" should not exist
    And the stored file "second-00000002.png" should exist
    When I send a "GET" request to "/_/orphaned_files"
    Then the JSON node "orphanedFiles" should list exactly the files "local:second-00000002.png, public_url_local:second-00000002.png"

  @loginAdmin
  Scenario: Deleting all orphaned files deletes everything a fresh scan reports and nothing else
    Given there is a stored file "first-00000001.png" older than the minimum age
    And there is a stored file "components/second-00000002.png" older than the minimum age
    And there is a stored file "uploading.png" written just now
    And there is a DummyUploadable with the file "image.png" saved as "referenced"
    And the stored file of the resource "referenced" is older than the minimum age
    When I send a "POST" request to "/_/orphaned_files/delete" with body:
      """
      {"all": true}
      """
    Then the response status code should be 200
    And the JSON node "deleted" should list exactly the files "local:first-00000001.png, local:components/second-00000002.png, public_url_local:first-00000001.png, public_url_local:components/second-00000002.png"
    And the JSON node "rejected" should have 0 elements
    And the stored file "first-00000001.png" should not exist
    And the stored file "components/second-00000002.png" should not exist
    And the stored file "uploading.png" should exist
    And the file for the resource "referenced" should exist in its configured filestore

  @loginAdmin
  Scenario: A path that stopped being orphaned between the scan and the delete is rejected and kept
    Given there is a stored file "claimed-0000000c.txt" older than the minimum age
    And there is a DummyUploadable with the file "image.png" saved as "claimant"
    And I send a "POST" request to "/_/orphaned_files/scan"
    And the resource "claimant" has a file "claimed-0000000c.txt"
    And I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"
    When I request the deletion of the orphaned files "claimed-0000000c.txt"
    Then the response status code should be 200
    And the JSON node "deleted" should have 0 elements
    And the JSON node "rejected[0].path" should be equal to "claimed-0000000c.txt"
    And the JSON node "rejected[0].reason" should be equal to "not_orphaned"
    And the stored file "claimed-0000000c.txt" should exist

  @loginAdmin
  Scenario: A missing-file entry cannot be deleted, and a path that does not exist is rejected cleanly
    Given there is a DummyUploadable with the file "image.png" saved as "broken"
    And the stored file for the resource "broken" has been removed from its filestore
    When I request the deletion of the orphaned file stored by the resource "broken"
    Then the response status code should be 200
    And the JSON node "deleted" should have 0 elements
    And the JSON node "rejected[0].path" should be equal to the stored path of the resource "broken"
    And the JSON node "rejected[0].reason" should be equal to "not_found"
    And the resource "broken" should have an uploaded file

  @loginAdmin
  Scenario Outline: A deletion request names paths or all, never both and never neither
    When I send a "POST" request to "/_/orphaned_files/delete" with body:
      """
      <body>
      """
    Then the response status code should be 422
    Examples:
      | body                                  |
      | {}                                    |
      | {"all": false}                        |
      | {"all": true, "paths": ["first-00000001.png"]} |

  Scenario: The scan command prints the counts, lists the files with -v, stores the report and deletes nothing
    Given there is a stored file "orphan-0a1b2c3d.png" older than the minimum age
    And there is a DummyUploadable with the file "image.png" saved as "broken"
    And the stored file for the resource "broken" has been removed from its filestore
    When I run the console command "silverback:api-components:scan-orphaned-files" verbosely
    Then the console command should have exited with 0
    And the console command output should contain "Orphaned files: 2"
    And the console command output should contain "  local: orphan-0a1b2c3d.png"
    And the console command output should contain "  public_url_local: orphan-0a1b2c3d.png"
    And the console command output should contain "Unknown files: 0"
    And the console command output should contain "Missing files: 1"
    And the stored file "orphan-0a1b2c3d.png" should exist
    And the stored orphaned files report should list 2 orphaned files and 1 missing file
