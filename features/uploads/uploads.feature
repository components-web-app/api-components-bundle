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
    And the header "content-disposition" should contain "inline; filename=image-"

  @loginUser
  Scenario: I get get the endpoint of the default media object
    Given there is a DummyUploadableWithImagineFilters
    When I request the download endpoint with the postfix "?download=true"
    Then the response status code should be 200
    And the header "content-type" should be equal to "image/png"
    And the header "content-disposition" should contain "attachment; filename=image-"

  # POST/UPDATE

  @loginUser
  Scenario Outline: I can update a media resource
    Given there is a DummyUploadableWithImagineFilters
    When I send a "PATCH" request to the resource "dummy_uploadable" with data:
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

  # Replicate a published resource not having an image, creating a draft by uploading and then publishing this draft and the published resource should have the image
  @loginAdmin
  Scenario: When we create a draft through means of an upload to the published, and then publish the draft, the new published resource should still have a file
    Given I add "Content-Type" header equal to "multipart/form-data"
    And there is a DummyUploadableAndPublishable
    When I send a "POST" request to the resource "dummy_uploadable" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.svg |
    Then the response status code should be 201
    And the response resource should be saved as "dummy_uploadable_draft"
    And I send a "PATCH" request to the resource "dummy_uploadable_draft" with body:
    """
    {
        "publishedAt": "1970-11-11T23:59:59+00:00"
    }
    """
    Then the response status code should be 200
    And the response should be the resource "dummy_uploadable"
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files_with_imagine.schema.json"
    And the JSON node "_metadata.mediaObjects.file[0].contentUrl" should be a valid download link for the resource "dummy_uploadable"
    And the resource "dummy_uploadable" should have a filename matching "#^components/image-[0-9a-f]{8}\.svg$#"
    And the file for the resource "dummy_uploadable" should exist in its configured filestore

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
    When I send a "PATCH" request to the resource "dummy_uploadable" with data:
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
    Given there is a DummyUploadableAndPublishable with a draft
    And there is a ComponentPosition with the resource "dummy_uploadable"
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt                |
      | 1970-11-11T23:59:59+00:00  |
    Then the response status code should be 200
    And the JSON node "componentPositions[0]" should exist
    And the resource "dummy_uploadable" should have a filename matching "#^components/image-[0-9a-f]{8}\.png$#"
    And the file for the resource "dummy_uploadable" should exist in its configured filestore

  @loginAdmin
  Scenario Outline: When I upload a new file to a publishable uploadable, the new draft should not have a component position and the original image should still exist
    Given there is a DummyUploadableAndPublishable
    And the resource "dummy_uploadable" has a file "<existing_file>"
    And there is a ComponentPosition with the resource "dummy_uploadable"
    When I send a "PATCH" request to the resource "dummy_uploadable" with data:
      | file               |
      | base64(<new_file>) |
    Then the response status code should be 200
    And the JSON node "componentPositions[0]" should not exist
    And the resource "dummy_uploadable" should have 1 component positions
    Examples:
      | existing_file    | new_file      |
      | existing.png     | image.png     |

  @loginAdmin
  Scenario: When I upload an image to a published resource to create a draft, the published image data and filename should remain in tact
    Given there is a DummyUploadableAndPublishable
    And I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to the resource "dummy_uploadable" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.svg |
    Then the response status code should be 201
    And the JSON node "_metadata.publishable.published" should be false
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files.schema.json"
    And the resource "dummy_uploadable" should have an uploaded file

  @loginAdmin
  Scenario: When I upload to overwrite a draft, cache should overwrite properly
    Given there is a DummyUploadableAndPublishable with a draft
    And I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to the resource "dummy_uploadable_draft" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.svg |
    Then the response status code should be 201

  # Publishing a draft that has a published resource runs the draft->published merge
  # (PublishableEventListener::mergeDraftIntoPublished), which deletes the published resource's own
  # file from the filestore before copying the draft's fields over it. Asserting the filename column
  # is not enough - the download link step only compares a URL built from the IRI, and the schema only
  # proves filename is non-null. These scenarios assert the stored object itself survives the merge.

  @loginAdmin
  Scenario: When I upload a file to an existing draft and then publish it, the published resource keeps the uploaded file
    Given there is a DummyUploadableAndPublishable with a draft
    And I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to the resource "dummy_uploadable_draft" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.svg |
    Then the response status code should be 201
    And I add "Content-Type" header equal to "application/merge-patch+json"
    And I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt               |
      | 1970-11-11T23:59:59+00:00 |
    Then the response status code should be 200
    And the response should be the resource "dummy_uploadable"
    And the JSON node "_metadata.publishable.published" should be true
    And the resource "dummy_uploadable" should have a filename matching "#^components/image-[0-9a-f]{8}\.svg$#"
    And the file for the resource "dummy_uploadable" should exist in its configured filestore

  # A draft that was never published has no publishedResource, so checkMergeDraftIntoPublished returns
  # early - no merge and no file deletion - and the resource keeps its own IRI once published.
  @loginAdmin
  Scenario: When I upload a file to a draft that has never been published and then publish it, the file is kept
    Given there is a draft DummyUploadableAndPublishable
    And I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to the resource "dummy_uploadable_draft" and the postfix "/upload" with parameters:
      | key  | value      |
      | file | @image.svg |
    Then the response status code should be 201
    And I add "Content-Type" header equal to "application/merge-patch+json"
    And I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt               |
      | 1970-11-11T23:59:59+00:00 |
    Then the response status code should be 200
    And the JSON node "_metadata.publishable.published" should be true
    And the resource "dummy_uploadable_draft" should have a filename matching "#^components/image-[0-9a-f]{8}\.svg$#"
    And the file for the resource "dummy_uploadable_draft" should exist in its configured filestore

  @loginUser
  Scenario: An uploadable field configured with urlGenerator public returns a direct public URL
    Given there is a DummyUploadablePublicUrl
    When I send a "GET" request to the resource "dummy_uploadable"
    Then the response status code should be 200
    And the JSON node "_metadata.mediaObjects.file[0].contentUrl" should start with "http://localhost/uploads/"

  @loginUser
  Scenario: An uploadable field configured with urlGenerator temporary falls back to the API download endpoint when the adapter does not support temporary URLs
    Given there is a DummyUploadableTemporaryUrl
    When I send a "GET" request to the resource "dummy_uploadable"
    Then the response status code should be 200
    And the JSON node "_metadata.mediaObjects.file[0].contentUrl" should be a valid download link for the resource "dummy_uploadable"

  # Multiple independent uploadable fields on one resource.
  # $file (generic, no imagine filters) and $preview (image, imagine filters) each have their own
  # storage property, so uploading to one never touches the other, and imagine only ever runs on
  # an actual image — never on a non-image (docx/pdf) even when the target field declares filters.

  @loginUser
  Scenario: Uploading a non-image to an imagine-filtered field does not attempt image processing
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_multiple_uploadables/upload" with parameters:
      | key     | value           |
      | preview | @test_file.docx |
    Then the response status code should be 201
    And the JSON node "_metadata.mediaObjects.preview[0].imagineFilter" should not exist
    And the JSON node "_metadata.mediaObjects.preview[1]" should not exist

  @loginUser
  Scenario: Uploading an image to an imagine-filtered field still produces the imagine variant
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_multiple_uploadables/upload" with parameters:
      | key     | value      |
      | preview | @image.png |
    Then the response status code should be 201
    And the JSON node "_metadata.mediaObjects.preview[0].imagineFilter" should not exist
    And the JSON node "_metadata.mediaObjects.preview[1].imagineFilter" should be equal to the string "thumbnail"

  @loginUser
  Scenario: Uploading to one uploadable field does not populate the other field
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_multiple_uploadables/upload" with parameters:
      | key  | value      |
      | file | @image.png |
    Then the response status code should be 201
    And the JSON node "_metadata.mediaObjects.file[0]" should exist
    And the JSON node "_metadata.mediaObjects.preview" should not exist

  # requiredOnPublish — a file must be present per flagged field before the resource can be published.

  @loginAdmin
  Scenario: Publishing is rejected per field when a requiredOnPublish file is missing
    Given there is a draft DummyUploadableRequiredOnPublish
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt |
      | now         |
    Then the response status code should be 422
    And the JSON node "violations" should have 2 elements
    And the JSON node "violations[0].propertyPath" should be equal to the string "file"
    And the JSON node "violations[0].message" should be equal to the string "You must upload a file before publishing."
    And the JSON node "violations[1].propertyPath" should be equal to the string "preview"
    And the JSON node "violations[1].message" should be equal to the string "A file must be uploaded for the `preview` field before publishing."

  @loginAdmin
  Scenario: Publishing is still rejected when only some requiredOnPublish files are present
    Given there is a draft DummyUploadableRequiredOnPublish with only the file uploaded
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt |
      | now         |
    Then the response status code should be 422
    And the JSON node "violations" should have 1 element
    And the JSON node "violations[0].propertyPath" should be equal to the string "preview"

  @loginAdmin
  Scenario: Publishing succeeds when all requiredOnPublish files are present
    Given there is a draft DummyUploadableRequiredOnPublish with all files uploaded
    And I add "Content-Type" header equal to "application/merge-patch+json"
    When I send a "PATCH" request to the resource "dummy_uploadable_draft" with data:
      | publishedAt |
      | now         |
    Then the response status code should be 200
    And the JSON node "_metadata.publishable.published" should be true

  @loginUser
  Scenario: A multipart file upload fires exactly one Mercure notification
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploadables/upload" with parameters:
      | key  | value      |
      | file | @image.png |
    Then the response status code should be 201
    And there should be 1 mercure messages

  # Files are stored under the uploaded file's own name plus a unique token, so
  # two resources that upload the same source file each get their own stored file
  # and one can never overwrite (or, when later deleted, remove) another's.
  # One resource is uploaded through the real multipart pipeline; a second is created from the same
  # source file via persistFiles directly. Both must be stored under a unique tokenised name derived
  # from the original filename, so neither can overwrite (or, on delete, remove) the other's file.
  @loginUser
  Scenario: Uploading keeps the original filename with a unique token and never collides with another resource's file
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploadables/upload" with parameters:
      | key  | value      |
      | file | @image.png |
    Then the response status code should be 201
    And the response resource should be saved as "first_upload"
    And there is a DummyUploadable with the file "image.png" saved as "second_upload"
    And the resource "first_upload" should have a filename matching "/^image-[^\/]+\.png$/"
    And the resource "second_upload" should have a filename matching "/^image-[^\/]+\.png$/"
    And the resource "second_upload" should have a different filename to the resource "first_upload"
    And the file for the resource "first_upload" should exist in its configured filestore
    And the file for the resource "second_upload" should exist in its configured filestore
