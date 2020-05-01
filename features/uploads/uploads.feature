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
    When I send a "POST" request to "/_/dummy_uploadables" with data:
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
    When I send a "POST" request to "/_/dummy_uploadables" with data:
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
    When I send a "POST" request to "/_/dummy_uploadables/upload" with parameters:
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
    When I send a "POST" request to "/_/dummy_uploadables/upload" with body:
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
    And the JSON node "_metadata.media_objects.filename[0].imagineFilter" should not exist
    And the JSON node "_metadata.media_objects.filename[1].imagineFilter" should contain "thumbnail"
    And the JSON node "_metadata.media_objects.filename[2].imagineFilter" should contain "square_thumbnail"

  # PUT

  @loginUser
  @wip
  Scenario: I can update a media resource

  @loginUser
  @wip
  Scenario: I can assign a media resource to a component

  # DELETE

  @loginUser
  @wip
  Scenario: I can update a media resource