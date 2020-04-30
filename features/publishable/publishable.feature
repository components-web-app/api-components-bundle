Feature: Access to unpublished/draft resources should be configurable
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # GET collection
  @loginAdmin
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, it should include the draft resources instead of the published ones.
    Given there are 2 draft and published resources available
    When I send a "GET" request to "/component/publishable_components"
    Then the response status code should be 200
    And the response should include the draft resources instead of the published ones

  @loginAdmin
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, and published=true query filter, it should include the published resources only.
    Given there are 2 draft and published resources available
    When I send a "GET" request to "/component/publishable_components?published=true"
    Then the response status code should be 200
    And the response should include the published resources only

  @loginUser
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, it should include the published resources only.
    Given there are 2 draft and published resources available
    When I send a "GET" request to "/component/publishable_components"
    Then the response status code should be 200
    And the response should include the published resources only

  @loginUser
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, and published=false query filter, it should not include the draft resources.
    Given there are 2 draft and published resources available
    When I send a "GET" request to "/component/publishable_components?published=false"
    Then the response status code should be 200
    And the response should include the published resources only

  # POST
  @loginAdmin
  Scenario: As a user with draft access, when I create a resource with publishedAt=null, I should be able to set the publishedAt date to specify if it is draft/published
    When I send a "POST" request to "/component/publishable_components" with data:
      | reference | publishedAt |
      | test      | null        |
    Then the response status code should be 201
    # publishedAt does not exist because it's null
    And the JSON should be valid according to the schema file "publishable.schema.json"
    And the JSON node publishedAt should not exist
    And the JSON node _metadata.published should be false

  @loginAdmin
  Scenario Outline: As a user with draft access, when I create a resource, I should be able to set the publishedAt date to specify if it is draft/published
    When I send a "POST" request to "/component/publishable_components" with data:
      | reference | publishedAt   |
      | test      | <publishedAt> |
    Then the response status code should be 201
    And the JSON node "publishedAt" should be equal to "<publishedAt>"
    And the JSON node _metadata.published should be equal to "<isPublished>"
    Examples:
      | publishedAt               | isPublished |
      | now                       | true        |
      | 1970-01-01T00:00:00+00:00 | true        |
      | 2999-12-31T23:59:59+00:00 | false       |

  @loginUser
  Scenario: As a user with no draft access, when I create a resource with publishedAt=null, I should have the published resource returned, and the publication date is automatically set.
    When I send a "POST" request to "/component/publishable_components" with data:
      | reference | publishedAt |
      | test      | null        |
    Then the response status code should be 201
    And the JSON node publishedAt should exist
    And the JSON node _metadata.published should be true

  @loginUser @saveNow
  Scenario Outline: As a user with no draft access, when I create a resource, I should have the published resource returned, and the publication date is automatically set.
    When I send a "POST" request to "/component/publishable_components" with data:
      | reference | publishedAt   |
      | test      | <publishedAt> |
    Then the response status code should be 201
    And the JSON node publishedAt should exist
    And the JSON node _metadata.published should be true
    And the JSON node "publishedAt" should be equal to "now"
    Examples:
      | publishedAt               |
      | now                       |
      | 1970-01-01T00:00:00+00:00 |
      | 2999-12-31T23:59:59+00:00 |

  # GET item
  @loginAdmin
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, I should have the draft resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "publishable_published"
    Then the response status code should be 200
    And the response should be the component "publishable_draft"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"
    And the JSON node "_metadata.published" should be equal to "false"
    And the JSON should be valid according to the schema file "publishable.schema.json"

  @loginUser
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, and published=true query filter, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "publishable_published" and the postfix "?published=true"
    Then the response status code should be 200
    And the response should be the component "publishable_published"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"
    And the JSON node "_metadata.published" should be equal to "true"
    And the JSON should be valid according to the schema file "publishable.schema.json"

  @loginAdmin
  Scenario: As a user with draft access, when I get a draft resource with published=true query filter, I should have a 404 error.
    Given there is a publishable resource set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "publishable_draft" and the postfix "?published=true"
    Then the response status code should be 404

  @loginUser
  Scenario: As any user, when I get a resource with a past publication date, and a draft resource available with an active publication date, the draft resource replaces the published one, and the old one is removed.
    Given there is a published resource with a draft set to publish at "2020-01-01T00:00:00+00:00"
    When I send a "GET" request to the component "publishable_published"
    Then the response status code should be 200
    And the response should be the component "publishable_published"
    And the component "publishable_draft" should not exist
    And the JSON node "publishedAt" should be equal to "2020-01-01T00:00:00+00:00"
    And the JSON should be valid according to the schema file "publishable.schema.json"
    And the header "expires" should not exist

  @loginUser
  Scenario Outline: As a user with no draft access, when I get a published resource with a draft resource available, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "publishable_published" and the postfix "?<querystring>"
    Then the response status code should be 200
    And the response should be the component "publishable_published"
    And the JSON should be valid according to the schema file "publishable.schema.json"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"
    Examples:
      | querystring     |
      | null            |
      | published=false |

  @loginAdmin
  Scenario: I can use a publishable entity with customised fields
    Given there is a custom publishable resource set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "publishable_draft"
    Then the response status code should be 200
    And the response should be the component "publishable_draft"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"
    And the JSON node "_metadata.published" should be equal to false
    And the JSON node "customPublishedAt" should be equal to "2999-12-31T23:59:59+00:00"
    And the response should not include the key "customPublishedResource"
    And the response should not include the key "customDraftResource"

  @loginAdmin
  Scenario Outline: when the publication date of a draft is reached, it should automatically merge with the published resource
    Given there is a published resource with a draft set to publish at "1970-12-31T23:59:59+00:00"
    When I send a "GET" request to the component "<requestComponent>"
    Then the response status code should be 200
    And the response should be the component "publishable_published"
    And the JSON node "reference" should be equal to is_draft
    And the header "expires" should not exist
    And the JSON node "_metadata.published" should be equal to true
    And the JSON should be valid according to the schema file "publishable.schema.json"
    And the component "publishable_draft" should not exist
    Examples:
      | requestComponent      |
      | publishable_draft     |
      | publishable_published |

  # PUT
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource, it should create and return a draft resource.
    Given there is a publishable resource set to publish at "1970-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response status code should be 200
    And the JSON node publishedAt should not exist
    And the JSON node _metadata.published should be false
    And the JSON node "reference" should be equal to "updated"

  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, it should update and return the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response status code should be 200
    And the JSON node "publishedAt" should be equal to "2999-12-31T23:59:59+00:00"
    And the JSON node "reference" should be equal to "updated"

  @loginAdmin
  Scenario Outline: As a user with draft access, when I update a published resource with a publication date in the past (or now), it should be ignored.
    Given there is a publishable resource set to publish at "1970-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_published" with data:
      | publishedAt   |
      | <publishedAt> |
    Then the response status code should be 200
    And the JSON node "publishedAt" should be equal to "1970-12-31T23:59:59+00:00"
    Examples:
      | publishedAt               |
      | 1970-01-01T00:00:00+00:00 |
      | now                       |

  @loginAdmin
  Scenario Outline: As a user with draft access, when I update a published/draft resource with a draft resource available, and set a publication date in the past (or now), it should update the draft resource, merge it with the public resource, and remove the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "<component>" with data:
      | publishedAt   | reference |
      | <publishedAt> | updated   |
    Then the response status code should be 200
    And the response should be the component "publishable_published"
    And the JSON node "reference" should be equal to "updated"
    And the JSON node "_metadata.published" should be equal to true
    And the JSON node "publishedAt" should be equal to "<publishedAt>"
    And the component "publishable_draft" should not exist
    And the header "expires" should not exist
    Examples:
      | publishedAt               | component             |
      | 1970-01-01T00:00:00+00:00 | publishable_published |
      | now                       | publishable_draft     |

  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the future, it should update and return the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "publishedAt": "2991-11-11T23:59:59+00:00"
    }
    """
    Then the response status code should be 200
    And the JSON node "publishedAt" should be equal to "2991-11-11T23:59:59+00:00"
    And the response should be the component "publishable_draft"

  @loginUser
  Scenario: As a user with no draft access, when I update a published resource, it should update and return the published resource.
    Given there is a publishable resource set to publish at "1970-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response status code should be 200
    And the JSON node "reference" should be equal to "updated"
    And the response should be the component "publishable_published"

  @loginUser
  Scenario: As a user with no draft access, I cannot update a draft resource.
    Given there is a publishable resource set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response status code should be 404

  @loginAdmin
  Scenario: I cannot modify the publishedResource property via the API
    Given there is a publishable resource set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated",
        "publishedResource": "something_else"
    }
    """
    Then the response status code should be 200
    And the JSON node "reference" should be equal to "updated"
    And the JSON node publishedResource should not exist

  # DELETE
  @loginUser
  Scenario: As any user, when I delete a published resource with a draft resource available, it should delete the published resource and keep the draft.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "DELETE" request to the component "publishable_published"
    Then the response status code should be 204
    And the component "publishable_published" should not exist
    And the component "publishable_draft" should exist

  @loginAdmin
  Scenario: As a user with draft access, I can delete a draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "DELETE" request to the component "publishable_draft"
    Then the response status code should be 204
    And the component "publishable_draft" should not exist
    And the component "publishable_published" should exist

  @loginAdmin
  Scenario: As a user with draft access, if I delete a published resource, it will delete the draft instead
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "DELETE" request to the component "publishable_published"
    Then the response status code should be 204
    And the component "publishable_draft" should not exist
    And the component "publishable_published" should exist

  @loginAdmin
  Scenario: As a user with draft access, if I delete a published resource, with the published=true querystring it will delete the published resource
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "DELETE" request to the component "publishable_published" and the postfix "?published=true"
    Then the response status code should be 204
    And the component "publishable_published" should not exist
    And the component "publishable_draft" should exist

  @loginUser
  Scenario: As a user with no draft access, I cannot delete a draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31T23:59:59+00:00"
    When I send a "DELETE" request to the component "publishable_draft"
    Then the response status code should be 404
    And the component "publishable_draft" should exist
    And the component "publishable_published" should exist
