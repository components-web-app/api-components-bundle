Feature: Gallery
  In order to support front-end galleries such as PhotoSwipe
  As a website user
  I the gallery item entity will return additional data

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
        "id": {
          "type": "integer"
        },
        "page": null,
        "sort": null,
        "group": null,
        "className": null
      }
    }
    """

  Scenario: Testing the gallery image response has extra nodes
    When I send a "POST" request to "/gallery_items" with body:
    """
    {
      "title": "Test Gallery Image",
      "filePath": "/images/testImage.jpg",
      "gallery": "galleries/1"
    }
    """
    Then the response status code should be 201
    And the JSON node width should be equal to the number 100
    And the JSON node height should be equal to the number 100
    And the JSON node thumbnailPath should be equal to the string "/media/cache/resolve/thumbnail/images/testImage.jpg"
    And the JSON node placeholderPath should be equal to the string "/media/cache/resolve/placeholder/images/testImage.jpg"

  Scenario: Test the thumbnail redirect is returned and image is generated
      When I send a "GET" request to "/media/cache/resolve/thumbnail/images/testImage.jpg"
      Then the response status code should be 301
      And the public file path "media/cache/thumbnail/images/testImage.jpg" should exist

  Scenario: Test the placeholder redirect is returned and image is generated
    When I send a "GET" request to "/media/cache/resolve/placeholder/images/testImage.jpg"
    Then the response status code should be 301
    And the public file path "media/cache/placeholder/images/testImage.jpg" should exist


  Scenario: Test the gallery image can be updated and the old cached resized images are removed
    When I send a "PUT" request to "/gallery_items/1" with body:
    """
    {
      "filePath": "/images/testImage2.jpg"
    }
    """
    Then the response status code should be 200
    And the JSON node filePath should be equal to the string "/images/testImage2.jpg"
    And the public file path "media/cache/placeholder/images/testImage.jpg" should not exist
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should not exist

  Scenario: Test the thumbnail redirect is returned and image is generated for new image
    When I send a "GET" request to "/media/cache/resolve/thumbnail/images/testImage2.jpg"
    Then the response status code should be 301
    And the public file path "media/cache/thumbnail/images/testImage2.jpg" should exist

  @dropSchema
  Scenario: Test the gallery image can be deleted and the cached resized images are removed
    When I send a "DELETE" request to "/gallery_items/1"
    Then the response status code should be 204
    And the public file path "media/cache/thumbnail/images/testImage2.jpg" should not exist