Feature: Gallery
  In order to support the Gallery and GalleryItem entity
  As a website user
  I will receive the correct data and http status codes with the available endpoints

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Create a gallery
    When I send a "POST" request to "/galleries" with body:
    """
    {}
    """
    Then the response status code should be 201
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "items": {
          "type": "array",
          "items": []
        },
        "id": { "type": "integer" },
        "page": { "type": "null" },
        "sort": { "type": "null" },
        "group": { "type": "null" },
        "className": { "type": "null" }
      }
    }
    """

  Scenario: Create a gallery item/image
    When I send a "POST" request to "/gallery_items" with body:
    """
    {
      "title": "Test Gallery Image",
      "filePath": "/images/testImage.jpg",
      "gallery": "galleries/1"
    }
    """
    Then the response status code should be 201
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "id": { "type": "integer" },
        "width": { "type": "integer" },
        "height": { "type": "integer" },
        "thumbnailPath": { "type": "string" },
        "placeholderPath": { "type": "string" },
        "page": { "type": "null" },
        "sort": { "type": "null" },
        "group": { "type": "null" },
        "className": { "type": "null" }
      }
    }
    """
    And the JSON node width should be equal to the number 100
    And the JSON node height should be equal to the number 100
    And the JSON node thumbnailPath should match "/\/media\/cache\/[^\/]+\/images\/testImage.jpg/"
    And the JSON node placeholderPath should match "/\/media\/cache\/[^\/]+\/images\/testImage.jpg/"
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should exist
    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should exist

  Scenario: Get a gallery
    When I send a "GET" request to "/galleries/1"
    Then the response status code should be 200
    And the JSON should be valid according to this schema:
    """
    {
      "type": "object",
      "properties": {
        "children": {
          "type": "array",
          "minItems": 1,
          "items": {
            "type": "object"
          }
        },
        "id": { "type": "integer" },
        "page": { "type": "null" },
        "sort": { "type": "null" },
        "group": { "type": "null" },
        "className": { "type": "null" }
      }
    }
    """

#  Scenario: An SVG image is uploaded and handled correctly
#    When I send a "PUT" request to "/gallery_items/1" with body:
#    """
#    {
#      "filePath": "/images/apiPlatform.svg"
#    }
#    """
#    Then the response status code should be 200
#    And the JSON node filePath should be equal to the string "/images/apiPlatform.svg"
#    And the JSON node thumbnailPath should be null
#    And the JSON node placeholderPath should be null
#    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should not exist
#    And the public file path "media/cache/thumbnail/images/testImage.jpg" should not exist
#
#  Scenario: Test the gallery image can be updated and the old cached resized images are removed
#    When I send a "PUT" request to "/gallery_items/1" with body:
#    """
#    {
#      "filePath": "/images/testImage2.jpg"
#    }
#    """
#    Then the response status code should be 200
#    And the JSON node filePath should be equal to the string "/images/testImage2.jpg"
#    And the JSON node thumbnailPath should match "/\/media\/cache\/[^\/]+\/images\/testImage2.jpg/"
#    And the JSON node placeholderPath should match "/\/media\/cache\/[^\/]+\/images\/testImage2.jpg/"

  Scenario: Delete a gallery
    When I send a "DELETE" request to "/galleries/1"
    Then the response status code should be 204
    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should not exist
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should not exist

#  @dropSchema
  Scenario: Check gallery item delete has persisted
    When I send a "GET" request to "/gallery_items/1"
    Then the response status code should be 404
