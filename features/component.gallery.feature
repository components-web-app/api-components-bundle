Feature: Gallery
  In order to support Galleries
  As a website user
  I can perform all CRUD operations necessary and retrieve the correct data back

  Background:
    Given I add "Content-Type" header equal to "application/ld+json"

  @createSchema
  Scenario: Create a gallery
    When I send a "POST" request to "/galleries" with body:
    """
    {}
    """
    Then the response status code should be 201
    And save the entity id as gallery

  Scenario: Create a gallery item/image
    Given the json variable gallery_item_post is:
    """
    {
      "title": "Test Gallery Image",
      "filePath": "/images/testImage.jpg"
    }
    """
    And the node parent of the json variable gallery_item_post is equal to the variable gallery
    When I send a "POST" request to "/gallery_items" with the json variable gallery_item_post as the body
    Then the response status code should be 201
    And save the entity id as gallery_item
    And the JSON should be valid according to the schema "features/bootstrap/json-schema/components/gallery_item.json"
    And the JSON node width should be equal to the number 100
    And the JSON node height should be equal to the number 100
    And the JSON node thumbnailPath should match "/\/media\/cache\/[^\/]+\/images\/testImage.jpg/"
    And the JSON node placeholderPath should match "/\/media\/cache\/[^\/]+\/images\/testImage.jpg/"
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should exist
    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should exist

  Scenario: An SVG image is uploaded and handled correctly
    When I send a "PUT" request to the entity gallery_item with body:
    """
    {
      "filePath": "/images/apiPlatform.svg"
    }
    """
    Then the response status code should be 200
    And the JSON node filePath should be equal to the string "/images/apiPlatform.svg"
    And the JSON node thumbnailPath should be null
    And the JSON node placeholderPath should be null
    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should not exist
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should not exist

  Scenario: Test the gallery image can be updated and the old cached resized images are removed
    When I send a "PUT" request to the entity gallery_item with body:
    """
    {
      "filePath": "/images/testImage2.jpg"
    }
    """
    Then the response status code should be 200
    And the JSON node filePath should be equal to the string "/images/testImage2.jpg"
    And the JSON node thumbnailPath should match "/\/media\/cache\/[^\/]+\/images\/testImage2.jpg/"
    And the JSON node placeholderPath should match "/\/media\/cache\/[^\/]+\/images\/testImage2.jpg/"

  Scenario: Delete a gallery
    When I send a DELETE request to the entity gallery
    Then the response status code should be 204

  @dropSchema
  Scenario: Check gallery item delete has persisted
    When I send a "GET" request to the entity gallery_item
    Then the response status code should be 404
    And the public file path "media/cache/placeholder_square/images/testImage.jpg" should not exist
    And the public file path "media/cache/thumbnail/images/testImage.jpg" should not exist
