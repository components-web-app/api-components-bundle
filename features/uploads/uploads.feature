Feature: API Resources which can have files uploaded
  In order to create a resource with a file
  As an API user
  I need to be able to create a resource and upload a file

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # POST

  @loginUser
  Scenario Outline: I can create a new dummy files component with a json base64 data
    When I send a "POST" request to "/_/dummy_uploadables" with data:
      | file          |
      | base64(<file>) |
    Then the response status code should be 201
    And the JSON should be valid according to the schema "features/assets/schema/<schema>"
    And the JSON node "filePath" should not exist
    Examples:
      | file          | schema                    |
      | image.svg     | file.schema.json          |
      | text_file.txt | file.schema.json          |

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
      | file          | schema                    |
      | image.svg     | file.schema.json          |
      | text_file.txt | file.schema.json          |

  @loginUser
  Scenario: I get an error if I send a json request to the multipart/form-data endpoint
    When I send a "POST" request to "/_/dummy_uploadables/upload" with body:
    """
    {}
    """
    Then the response status code should be 415

  # GET

  @loginUser
  @wip
  Scenario: I can get an image media resource with and without imagine filters configured

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