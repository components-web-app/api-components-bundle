Feature: Access to unpublished/draft resources should be configurable
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # GET collection
  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, it should include the draft resources instead of the published ones.
    Given there are draft and published resources available
    When I send a "GET" request to "/component/publishable_components"
    Then the response should include the draft resources instead of the published ones

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I get a collection of published resources with draft resources available, and published=true query filter, it should include the published resources only.
    Given there are draft and published resources available
    When I send a "GET" request to "/component/publishable_components?published=true"
    Then the response should include the published resources only

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, it should include the published resources only.
    Given there are draft and published resources available
    When I send a "GET" request to "/component/publishable_components"
    Then the response should include the published resources only

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, when I get a collection of published resources with draft resources available, and published=false query filter, it should not include the draft resources.
    Given there are draft and published resources available
    When I send a "GET" request to "/component/publishable_components?published=false"
    Then the response should include the published resources only

  # POST
  @createSchema
  @loginAdmin
  Scenario Outline: As a user with draft access, when I create a resource, I should be able to set the publishedAt date to specify if it is draft/published
    When I send a "POST" request to "/component/publishable_components" with body:
    """
    {
      "reference": "test",
      "publishedAt": "<publishedAt>"
    }
    """
    Then the response should include the key "publishedAt" with the value "<publishedAt>"
    Examples:
      | publishedAt         |
      | null                |
      | now                 |
      | 1970-01-01 00:00:00 |
      | 2999-12-31 23:59:59 |

  @createSchema
  @loginUser
  Scenario Outline: As a user with no draft access, when I create a resource, I should have the published resource returned, and the publication date is automatically set.
    When I send a "POST" request to "/component/publishable_components" with body:
    """
    {
      "reference": "test",
      "publishedAt": "<publishedAt>"
    }
    """
    Then the response should be a published resource
    Examples:
      | publishedAt         |
      | null                |
      | now                 |
      | 1970-01-01 00:00:00 |
      | 2999-12-31 23:59:59 |

  # GET item
  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, I should have the draft resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published"
    Then the response should be the component "publishable_draft"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"

  @createSchema
  @loginUser
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, and published=true query filter, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published" and the postfix "?published=true"
    Then the response should be the component "publishable_published"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I get a draft resource with published=true query filter, I should have a 404 error.
    Given there is a publishable resource set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published" and the postfix "?published=true"
    Then the response status code should be 404
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"

  @createSchema
  Scenario: As any user, when I get a resource with a past publication date, and a draft resource available with an active publication date, the draft resource replaces the published one, and the old one is removed.
    Given there is a published resource with a draft set to publish at "2020-01-01 00:00:00"
    When I send a "GET" request to the component "publishable_published"
    Then the response should be the component "publishable_published"
    And the header "expires" should not exist
    And the response should include the key "publishedAt" with the value "2020-01-01 00:00:00"

  @createSchema
  @loginUser
  Scenario Outline: As a user with no draft access, when I get a published resource with a draft resource available, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published" and the postfix "?<querystring>"
    Then the response should be the component "publishable_published"
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"
    Examples:
      | querystring     |
      | null            |
      | published=false |

  # PUT
  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource, it should create and return a draft resource.
    Given there is a publishable resource set to publish at "1970-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response should be a draft resource
    And the response should include the key "publishedAt" with the value "null"
    And the response should include the key "reference" with the value "updated"

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, it should update and return the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response should include the key "publishedAt" with the value "2999-12-31 23:59:59"
    And the response should include the key "reference" with the value "updated"

  @createSchema
  @loginAdmin
  Scenario Outline: As a user with draft access, when I update a published resource with a publication date in the past (or now), it should be ignored.
    Given there is a publishable resource set to publish at "1970-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "publishedAt": "<publishedAt>"
    }
    """
    Then the response should include the key "publishedAt" with the value "1970-12-31 23:59:59"
    Examples:
      | publishedAt         |
      | 1970-01-01 00:00:00 |
      | now                 |

  @createSchema
  @loginAdmin
  Scenario Outline: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the past (or now), it should update and return the published resource, and remove the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "publishedAt": "<publishedAt>"
    }
    """
    Then the response should include the key "publishedAt" with the value "<publishedAt>"
    And the response should be the component "publishable_published"
    And the component "publishable_draft" should not exist
    Examples:
      | publishedAt         |
      | 1970-01-01 00:00:00 |
      | now                 |

#  NOTE: I thikn this test is redundant. A publication date in the future would signify it is a draft.
#  @createSchema
#  @loginAdmin
#  Scenario: As a user with draft access, when I update a published resource with a publication date in the future, it should create and return a draft resource.

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the future, it should update and return the draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "publishedAt": "2991-11-11 23:59:59"
    }
    """
    Then the response should include the key "publishedAt" with the value "2991-11-11 23:59:59"
    And the response should be the component "publishable_published"

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, when I update a published resource, it should update and return the published resource.
    Given there is a publishable resource set to publish at "1970-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_published" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response should include the key "reference" with the value "updated"
    And the response should be the component "publishable_published"

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, I cannot update a draft resource.
    Given there is a publishable resource set to publish at "2999-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated"
    }
    """
    Then the response status code should be 403

# Same as line 151 :: Scenario Outline: As a user with draft access, when I update a published resource with a publication date in the past (or now), it should be ignored.
#  @createSchema
#  @loginUser
#  Scenario: As a user with no draft access, when I update the publication date of a published resource, the publication date is not changed.

  #Security
  @createSchema
  @loginAdmin
  Scenario: I cannot modify the publishedResource property via the API
    Given there is a publishable resource set to publish at "2999-12-31 23:59:59"
    When I send a "PUT" request to the component "publishable_draft" with body:
    """
    {
        "reference": "updated",
        "publishedResource": "something_else"
    }
    """
    Then the response status code should be 200
    And the response should include the key "reference" with the value "updated"
    And the response should include the key "publishedResource" with the value "null"

  # DELETE
  @createSchema
  Scenario: As any user, when I delete a published resource with a draft resource available, it should delete the published resource and keep the draft.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "DELETE" request to the component "publishable_published"
    Then the response status code should be 200
    And the component "publishable_published" should not exist
    And the component "publishable_draft" should exist

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, I can delete a draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "DELETE" request to the component "publishable_draft"
    Then the response status code should be 200
    And the component "publishable_draft" should not exist
    And the component "publishable_published" should exist

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, if I delete a published resource, it will delete the draft instead
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "DELETE" request to the component "publishable_published"
    Then the response status code should be 200
    And the component "publishable_draft" should not exist
    And the component "publishable_published" should exist

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, if I delete a published resource, with the published=true querystring it will delete the published resource
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "DELETE" request to the component "publishable_published" and the postfix "?published=true"
    Then the response status code should be 200
    And the component "publishable_published" should not exist
    And the component "publishable_draft" should exist

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, I cannot delete a draft resource.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "DELETE" request to the component "publishable_draft"
    Then the response status code should be 403
    And the component "publishable_draft" should exist
    And the component "publishable_published" should exist
