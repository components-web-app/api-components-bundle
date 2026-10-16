Feature: An image upload field limited by the UploadedImage constraint
  In order to keep image uploads within what the server can process
  As an API user
  I need an image over the pixel limit to be refused, and an SVG to be accepted without pixel checks

  Background:
    Given I add "Accept" header equal to "application/ld+json"

  @loginUser
  Scenario: An image over the configured pixel limit is refused with the limit and an approximate size
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploaded_images/upload" with parameters:
      | key  | value      |
      | file | @image.png |
    Then the response status code should be 422
    And the JSON node "violations[0].propertyPath" should be equal to the string "file"
    And the JSON node "violations[0].message" should be equal to the string "The image is too large (357500 pixels). Resize it to at most 300000 pixels (about 670 x 447) and upload it again."
    And the JSON node "violations[1]" should not exist

  @loginUser
  Scenario Outline: An SVG skips the pixel checks, and the other file options still apply
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploaded_images/upload" with parameters:
      | key  | value   |
      | file | @<file> |
    Then the response status code should be <status>
    Examples:
      | file          | status |
      | image.svg     | 201    |
      | unsized.svg   | 201    |
      | test_file.txt | 422    |

  @loginAdmin
  Scenario: The orphaned files report checks a stored image against the same configured limit
    Given there is a DummyUploadedImage with the file "image.png" saved as "too_many_pixels"
    And there is a DummyUploadedImage with the file "unsized.svg" saved as "svg"
    When I send a "POST" request to "/_/orphaned_files/scan"
    And I send a "GET" request to "/_/orphaned_files"
    Then the response status code should be 200
    And the JSON node "invalidFiles" should have 1 element
    And the JSON node "invalidFiles[0].resource" should be equal to the IRI of the resource "too_many_pixels"
    And the JSON node "invalidFiles[0].violations[0]" should be equal to "The image is too large (357500 pixels). Resize it to at most 300000 pixels (about 670 x 447) and upload it again."
