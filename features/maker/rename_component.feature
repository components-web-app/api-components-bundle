Feature: make:rename-component migration
  In order to rename a component type without losing data
  As a developer
  I need the generated migration to rewrite the discriminator and allowedComponents with real collection IRIs

  Scenario: A class that does not exist yet resolves to a blank node, so the maker refuses without --new-iri
    When I generate a rename component migration with:
      | old-name  | DummyComponent                                                              |
      | new-name  | RenamedComponent                                                            |
      | old-fqcn  | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent |
      | new-fqcn  | App\Entity\Component\RenamedComponent                                       |
      | old-dtype | dummycomponent                                                              |
      | new-dtype | renamedcomponent                                                            |
    Then API Platform resolves the class "App\Entity\Component\RenamedComponent" to a blank node IRI
    And the rename component migration should be refused naming the option "--new-iri"

  Scenario: Both classes resolve, so the migration uses both collection IRIs without asking
    When I generate a rename component migration with:
      | old-name  | DummyComponent                                                                   |
      | new-name  | RestrictedComponent                                                              |
      | old-fqcn  | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent      |
      | new-fqcn  | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent |
      | old-dtype | dummycomponent                                                                   |
      | new-dtype | restrictedcomponent                                                              |
    Then the generated rename component migration should rename "/component/dummy_components" to "/component/restricted_components"

  Scenario: The generated migration rewrites allowedComponents and the discriminator, and reverts them
    Given there is a ComponentGroup with 1 components
    And the ComponentGroup has the allowedComponent "/component/dummy_components"
    When I generate a rename component migration with:
      | old-name  | DummyComponent                                                              |
      | new-name  | RenamedComponent                                                            |
      | old-fqcn  | Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent |
      | new-fqcn  | App\Entity\Component\RenamedComponent                                       |
      | old-dtype | dummycomponent                                                              |
      | new-dtype | renamedcomponent                                                            |
      | new-iri   | /component/renamed_components                                               |
    Then the generated rename component migration should rename "/component/dummy_components" to "/component/renamed_components"
    When I run the generated rename component migration "up"
    Then every component group should allow exactly "/component/renamed_components"
    And every component should have the discriminator "renamedcomponent"
    When I run the generated rename component migration "down"
    Then every component group should allow exactly "/component/dummy_components"
    And every component should have the discriminator "dummycomponent"
