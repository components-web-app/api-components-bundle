Feature: Soft validation on draft resources
  In order to allow modification of resources in a draft
  As a an API user
  I must be able to configure validation which can fail while the resource is in a draft state

  Background:
    Given I add "Accept" header equal to "application/ld+json"
    And I add "Content-Type" header equal to "application/ld+json"

  # GET
  @loginAdmin
  Scenario Outline: When I retrieve a draft resource, there should be a header to indicate whether validation is passing if I were to try and publish it
    Given there is a DummyPublishableWithValidation resource
    When I send a "PUT" request to the component "publishable_draft" with data:
      | resourceData |
      | <data>       |
    Then the response status code should be 200
    And the header "valid-to-publish" should be equal to "<headerValue>"
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | data            | headerValue | schema                  |
      | valid_draft     | 0           | publishable.schema.json |
      | valid_published | 1           | publishable.schema.json |

  @loginAdmin
  Scenario Outline: I retrieve a draft resource which is invalid for publishing with a querystring "validate_published=true" I should receive validation errors
    Given there is a DummyPublishableWithValidation resource
    When I send a "GET" request to the component "publishable_draft" and the postfix "?<postfix>"
    Then the response status code should be <statusCode>
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | postfix                  | schema                        | statusCode |
      | validate_published=true  | validation_errors.schema.json | 400        |
      | validate_published=false | publishable.schema.json       | 200        |

  # PUT
  @loginAdmin
  Scenario Outline: I update a draft resource with data that is OK for a draft, but not for published
    Given there is a DummyPublishableWithValidation resource
    When I send a "PUT" request to the component "publishable_draft" and the postfix "?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should be equal to "<validToPublish>"
    And the JSON should be valid according to the schema file "<schema>"
    And the JSON node "_metadata.violation_list.violations[0]" should exist
    Examples:
      | publishedAt | data            | httpStatus | validToPublish | postfix                  | schema                        |
      | null        | valid_draft     | 200        | 0              | validate_published=false | publishable.schema.json       |

  @loginAdmin
  Scenario Outline: I update a draft resource with data that is valid to make it published when ready
    Given there is a DummyPublishableWithValidation resource
    When I send a "PUT" request to the component "publishable_draft" and the postfix "?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should be equal to "<validToPublish>"
    And the JSON should be valid according to the schema file "<schema>"
    And the JSON node "_metadata.violation_list.violations[0]" should not exist
    Examples:
      | publishedAt | data            | httpStatus | validToPublish | postfix                  | schema                        |
      | null        | valid_published | 200        | 1              | validate_published=false | publishable.schema.json       |
      | null        | valid_published | 200        | 1              | validate_published=true  | publishable.schema.json       |

  @loginAdmin
  Scenario Outline: I update a draft resource and expect to see a hard fail with validation errors and no need to populate metadata as the output is the violations
    Given there is a DummyPublishableWithValidation resource
    When I send a "PUT" request to the component "publishable_draft" and the postfix "?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should be equal to "<validToPublish>"
    And the JSON should be valid according to the schema file "<schema>"
    And the JSON node "_metadata.violation_list.violations[0]" should not exist
    Examples:
      | publishedAt | data            | httpStatus | validToPublish | postfix                  | schema                        |
      | null        | invalid_draft   | 400        | 0              | validate_published=false | validation_errors.schema.json |
      | null        | invalid_draft   | 400        | 0              | validate_published=true  | validation_errors.schema.json |
      | null        | valid_draft     | 400        | 0              | validate_published=true  | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | 0              | validate_published=false | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | 0              | validate_published=true  | validation_errors.schema.json |
      | now         | valid_draft     | 400        | 0              | validate_published=true  | validation_errors.schema.json |
      | now         | valid_draft     | 400        | 0              | validate_published=false | validation_errors.schema.json |

  @loginAdmin
  Scenario Outline: Updating a resource to published. The querystring should make no difference and the response is published so no header should exist
    Given there is a DummyPublishableWithValidation resource
    When I send a "PUT" request to the component "publishable_draft" and the postfix "?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should not exist
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | publishedAt | data            | httpStatus | postfix                  | schema                        |
      | now         | valid_published | 200        | validate_published=true  | publishable.schema.json       |
      | now         | valid_published | 200        | validate_published=false | publishable.schema.json       |

  @loginAdmin
  Scenario: I update a published resource with the querystring "validate_published=false" and "published=true" should have no effect and published resource validation should still apply
    Given there is a DummyPublishableWithValidation resource set to publish at "1970-12-31T23:59:59+00:00"
    When I send a "PUT" request to the component "publishable_published" and the postfix "?validate_published=false&published=true" with body:
     """
     {
       "description": ""
     }
     """
    Then the response status code should be 400
    And the JSON should be valid according to the schema file "validation_errors.schema.json"

  # POST
  @loginAdmin
  Scenario Outline: I create a valid draft resource
    When I send a "POST" request to "/_/dummy_publishable_with_validations" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should be equal to "<validToPublish>"
    And the JSON should be valid according to the schema file "publishable.schema.json"
    Examples:
      | publishedAt | data            | httpStatus | validToPublish |
      | null        | valid_draft     | 201        | 0              |
      | null        | valid_published | 201        | 1              |

  @loginAdmin
  Scenario Outline: I create a resource that is a draft with invalid properties for a published state, should still create resource
    When I send a "POST" request to "/_/dummy_publishable_with_validations?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should not exist
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | publishedAt | data            | httpStatus | postfix                  | schema                        |
      | null        | valid_draft     | 400        | validate_published=true  | validation_errors.schema.json |
      | null        | invalid_draft   | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | valid_draft     | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | valid_published | 201        | validate_published=true  | publishable.schema.json       |
      | null        | invalid_draft   | 400        | validate_published=false | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | validate_published=false | validation_errors.schema.json |
      | now         | valid_draft     | 400        | validate_published=false | validation_errors.schema.json |
      | now         | valid_published | 201        | validate_published=false | publishable.schema.json       |

  # POST - custom validation groups
  @loginAdmin
  Scenario Outline: I configure custom validation groups to create a draft resource
    When I send a "POST" request to "/_/dummy_publishable_with_validation_custom_groups" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the JSON should be valid according to the schema file "publishable.schema.json"
    And the response status code should be <httpStatus>
    And the header "valid-to-publish" should be equal to "<validToPublish>"
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | publishedAt | data            | httpStatus | validToPublish | schema                        |
      | null        | valid_published | 201        | 1              | publishable.schema.json       |
      | null        | valid_draft     | 201        | 0              | publishable.schema.json       |

  @loginAdmin
  Scenario Outline: I configure custom validation groups to validate/create a published resource
    When I send a "POST" request to "/_/dummy_publishable_with_validation_custom_groups?<postfix>" with data:
      | publishedAt   | resourceData |
      | <publishedAt> | <data>       |
    Then the response status code should be <httpStatus>
    And the header "valid-to-publish" should not exist
    And the JSON should be valid according to the schema file "<schema>"
    Examples:
      | publishedAt | data            | httpStatus | postfix                  | schema                        |
      | null        | valid_draft     | 400        | validate_published=true  | validation_errors.schema.json |
      | null        | invalid_draft   | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | valid_draft     | 400        | validate_published=true  | validation_errors.schema.json |
      | now         | valid_published | 201        | validate_published=true  | publishable.schema.json       |
      | null        | invalid_draft   | 400        | validate_published=false | validation_errors.schema.json |
      | now         | invalid_draft   | 400        | validate_published=false | validation_errors.schema.json |
      | now         | valid_draft     | 400        | validate_published=false | validation_errors.schema.json |
      | now         | valid_published | 201        | validate_published=false | publishable.schema.json       |
