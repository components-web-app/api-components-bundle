Feature: API Resources which can have files uploaded
  In order to create a resource with a file
  As an API user
  I need to be able to create a resource and upload a file

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # POST

  @loginUser
  Scenario Outline: I can create a new dummy files component with a json base64 data (and dataURI as that is how symfony serializes text files)
    When I send a "POST" request to "/dummy_uploadables" with data:
      | file           |
      | base64(<file>) |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file           | schema                           |
      | image.svg      | uploadable_has_files.schema.json |
      | image.png      | uploadable_has_files.schema.json |
      | test_file.txt  | uploadable_has_files.schema.json |
      | test_file.docx | uploadable_has_files.schema.json |

  @loginUser
  Scenario Outline: I can create a new dummy files component with base64 data that is just a string (no data:)
    When I send a "POST" request to "/dummy_uploadables" with data:
      | file                 |
      | base64string(<file>) |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file      | schema                           |
      | image.svg | uploadable_has_files.schema.json |

  @loginUser
  Scenario Outline: I can create a new dummy files component with a "multipart/form-data" request
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploadables/upload" with parameters:
      | key  | value   |
      | file | @<file> |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file           | schema                           |
      | image.png      | uploadable_has_files.schema.json |
      | image.svg      | uploadable_has_files.schema.json |
      | test_file.txt  | uploadable_has_files.schema.json |
      | test_file.docx | uploadable_has_files.schema.json |

  @loginUser
  Scenario: I get an error if I send a json request to the multipart/form-data endpoint
    When I send a "POST" request to "/dummy_uploadables/upload" with body:
    """
    {}
    """
    Then the response status code should be 415

  # GET

  # See issue: https://github.com/api-platform/core/issues/4825
  # possibly deprecated
  @loginUser
  Scenario: I can get an image media resource with imagine filters configured
    Given there is a DummyUploadableWithImagineFilters
    When I send a "GET" request to the resource "dummy_uploadable"
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files_with_imagine.schema.json"
    And the JSON node "_metadata.mediaObjects.file[0].contentUrl" should be a valid download link for the resource "dummy_uploadable"
    And the JSON node "_metadata.mediaObjects.file[0].@type" should be equal to the string "http://schema.org/MediaObject"
#    And the JSON node "_metadata.mediaObjects.file[0].@context.formattedFileSize" should be equal to the string "http://schema.org/contentSize"
#    And the JSON node "_metadata.mediaObjects.file[0].@context.contentUrl" should be equal to the string "http://schema.org/contentUrl"
#    And the JSON node "_metadata.mediaObjects.file[0].@context.mimeType" should be equal to the string "http://schema.org/encodingFormat"
#    And the JSON node "_metadata.mediaObjects.file[0].@context.width" should be equal to the string "http://schema.org/width"
#    And the JSON node "_metadata.mediaObjects.file[0].@context.height" should be equal to the string "http://schema.org/height"
    And the JSON node "_metadata.mediaObjects.file[0].imagineFilter" should not exist
    And the JSON node "_metadata.mediaObjects.file[1].imagineFilter" should be equal to the string "thumbnail"
    And the JSON node "_metadata.mediaObjects.file[1].width" should be equal to the number "350"
    And the JSON node "_metadata.mediaObjects.file[1].height" should be equal to the number "500"
    And the JSON node "_metadata.mediaObjects.file[1].mimeType" should be equal to the string "image/png"
    And the JSON node "_metadata.mediaObjects.file[2].imagineFilter" should be equal to the string "square_thumbnail"

  @loginUser
  Scenario: I get get the endpoint of the default media object
    Given there is a DummyUploadableWithImagineFilters
    When I request the download endpoint
    Then the response status code should be 200
    And the header "content-type" should be equal to "image/png"
    And the header "content-disposition" should be equal to "inline; filename=image.png"

  @loginUser
  Scenario: I get get the endpoint of the default media object
    Given there is a DummyUploadableWithImagineFilters
    When I request the download endpoint with the postfix "?download=true"
    Then the response status code should be 200
    And the header "content-type" should be equal to "image/png"
    And the header "content-disposition" should be equal to "attachment; filename=image.png"

  # POST/UPDATE

  @loginUser
  Scenario Outline: I can update a media resource
    Given there is a DummyUploadableWithImagineFilters
    When I send a "PUT" request to the resource "dummy_uploadable" with data:
      | file           |
      | base64(<file>) |
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file      | schema                           |
      | image.png | uploadable_has_files.schema.json |

  @loginAdmin
  Scenario: When an uploadable resource is also publishable, uploading a resource creates a draft
    Given I add "Content-Type" header equal to "multipart/form-data"
    And there is a DummyUploadableAndPublishable
    When I send a "POST" request to the resource "dummy_uploadable" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.png |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files.schema.json"
    And the JSON node "_metadata.publishable.published" should be false

  @loginAdmin
  Scenario: When I publish an uploadable component, the file should still exist and media object returned
    Given there is a draft DummyUploadableAndPublishable
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt   |
      | now           |
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files.schema.json"
    And the JSON node "_metadata.publishable.published" should be true

  # DELETE

  @loginAdmin
  Scenario: I can set the file to null to delete it, in a publishable component this creates a draft
    And there is a DummyUploadableAndPublishable
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable" with data:
      | file   |
      | null   |
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_no_files.schema.json"
    And the JSON node "_metadata.publishable.published" should be false
    And the resource dummy_uploadable should have an uploaded file

  @loginAdmin
  Scenario: I can set the file to null to delete it
    And there is a DummyUploadableWithImagineFilters
    When I send a "PUT" request to the resource "dummy_uploadable" with data:
      | file   |
      | null   |
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_no_files.schema.json"
    And the resource dummy_uploadable should not have an uploaded file

  @loginUser
  Scenario: I can delete a media resource
    Given there is a DummyUploadableWithImagineFilters
    When I send a "DELETE" request to the resource "dummy_uploadable"
    Then the response status code should be 204

  @loginAdmin
  Scenario: When I publish a draft image where a published image exists, the component positions should be present on the newly published resource
    And there is a DummyUploadableAndPublishable with a draft
    And there is a ComponentPosition with the resource "dummy_uploadable"
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt                |
      | 1970-11-11T23:59:59+00:00  |
    Then the response status code should be 200
    And the JSON node "componentPositions[0]" should exist
