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
      | file           | schema                            |
      | image.png      | uploadable_has_files.schema.json  |
      | image.svg      | uploadable_has_files.schema.json  |
      | test_file.txt  | uploadable_has_files.schema.json  |
      | test_file.docx | uploadable_has_files.schema.json  |

  @loginUser
  Scenario Outline: I can create a new dummy files component with base64 data that is just a string (no data:)
    When I send a "POST" request to "/dummy_uploadables" with data:
      | file                 |
      | base64string(<file>) |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file           | schema                            |
      | image.svg      | uploadable_has_files.schema.json  |

  @loginUser
  Scenario Outline: I can create a new dummy files component with a "multipart/form-data" request
    Given I add "Content-Type" header equal to "multipart/form-data"
    When I send a "POST" request to "/dummy_uploadables/upload" with parameters:
      | key    | value     |
      | file   | @<file>   |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file           | schema                            |
      | image.png      | uploadable_has_files.schema.json  |
      | image.svg      | uploadable_has_files.schema.json  |
      | test_file.txt  | uploadable_has_files.schema.json  |
      | test_file.docx | uploadable_has_files.schema.json  |

  @loginUser
  Scenario: I get an error if I send a json request to the multipart/form-data endpoint
    When I send a "POST" request to "/dummy_uploadables/upload" with body:
    """
    {}
    """
    Then the response status code should be 415

  # GET

  @loginUser
  Scenario: I can get an image media resource with imagine filters configured
    Given there is a DummyUploadableWithImagineFilters
    When I send a "GET" request to the component "dummy_uploadable"
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/uploadable_has_files_with_imagine.schema.json"
    And the JSON node "_metadata.media_objects.file[0].contentUrl" should be a valid download link for the component "dummy_uploadable"
    And the JSON node "_metadata.media_objects.file[0].@type" should be equal to the string "http://schema.org/MediaObject"
    And the JSON node "_metadata.media_objects.file[0].@context.formattedFileSize" should be equal to the string "http://schema.org/contentSize"
    And the JSON node "_metadata.media_objects.file[0].@context.contentUrl" should be equal to the string "http://schema.org/contentUrl"
    And the JSON node "_metadata.media_objects.file[0].@context.mimeType" should be equal to the string "http://schema.org/encodingFormat"
    And the JSON node "_metadata.media_objects.file[0].@context.width" should be equal to the string "http://schema.org/width"
    And the JSON node "_metadata.media_objects.file[0].@context.height" should be equal to the string "http://schema.org/height"
    And the JSON node "_metadata.media_objects.file[0].imagineFilter" should not exist
    And the JSON node "_metadata.media_objects.file[1].imagineFilter" should be equal to the string "thumbnail"
    And the JSON node "_metadata.media_objects.file[1].width" should be equal to the number "350"
    And the JSON node "_metadata.media_objects.file[1].height" should be equal to the number "500"
    And the JSON node "_metadata.media_objects.file[1].mimeType" should be equal to the string "image/png"
    And the JSON node "_metadata.media_objects.file[2].imagineFilter" should be equal to the string "square_thumbnail"

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

  # PUT

  @loginUser
  Scenario Outline: I can update a media resource
    Given there is a DummyUploadableWithImagineFilters
    When I send a "PUT" request to the component "dummy_uploadable" with data:
      | file           |
      | base64(<file>) |
    Then the response status code should be 200
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file           | schema                            |
      | image.png      | uploadable_has_files.schema.json  |

  # DELETE

  @loginUser
  Scenario: I can delete a media resource
    Given there is a DummyUploadableWithImagineFilters
    When I send a "DELETE" request to the component "dummy_uploadable"
    Then the response status code should be 204
