Feature: Access to unpublished/draft resources should be configurable
  In order to restrict access to draft components
  As a an application developer
  I must be able to configure the ability to access the resource

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
    Then the response should be a draft resource
    And the header "expires" should contain "Tue, 31 Dec 2999 23:59:59 GMT"

  @createSchema
  @loginUser
  Scenario: As a user with draft access, when I get a published resource with a draft resource available, and published=true query filter, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published" and the postfix "?published=true"
    Then the response should be a published resource
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
    Then the response should be a published resource
    And the header "expires" should not exist
    And the response should include the key "publishedAt" with the value "2020-01-01 00:00:00"

  @createSchema
  @loginUser
  Scenario Outline: As a user with no draft access, when I get a published resource with a draft resource available, I should have the published resource returned.
    Given there is a published resource with a draft set to publish at "2999-12-31 23:59:59"
    When I send a "GET" request to the component "publishable_published" and the postfix "?<querystring>"
    Then the response should be a published resource
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
    Then the response should be a draft resource

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a publication date in the past (or now), it should update and return the published resource.
  # Use Scenario Outline to check "in the past" and "now"

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the past (or now), it should update and return the published resource, and remove the draft resource.
  # Use Scenario Outline to check "in the past" and "now"

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a publication date in the future, it should create and return a draft resource.

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, when I update a published resource with a draft resource available, and set a publication date in the future, it should update and return the draft resource.

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, when I update a published resource, it should update and return the published resource.

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, I cannot update a draft resource.

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, when I update the publication date of a published resource, the publication date is not changed.

  # DELETE
  @createSchema
  Scenario: As any user, when I delete a published resource with a draft resource available, it should delete the published resource and keep the draft.

  @createSchema
  @loginAdmin
  Scenario: As a user with draft access, I can delete a draft resource.

  @createSchema
  @loginUser
  Scenario: As a user with no draft access, I cannot delete a draft resource.

  #Security
  @createSchema
  @loginAdmin
  Scenario: I cannot modify the publishedResource property via the API
