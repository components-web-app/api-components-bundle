Feature: Generated fixtures keep their uploaded files in a content-addressed asset store
  In order to keep a large site's uploaded files out of the repository and the image
  As a developer
  I need generate-fixtures to store each file once under its content hash, list it in a manifest, and the scaffold to load it back through the same store

  Scenario: With no asset store configured, files are still written beside the scaffold under readable names
    Given the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures
    Then the generated fixtures should contain "new File(__DIR__ . '/assets/"
    And the generated fixtures should not contain "$cwa->asset("
    And no fixture asset manifest should have been written

  Scenario: The local store beside the scaffold reloads as the same site and a second generation changes nothing
    Given the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures with the option "--assets-store" set to "local" and reloaded
    Then the generated fixtures should contain "$cwa->asset('"
    And the generated fixtures should not contain "new File("
    And the fixture asset store should hold exactly the 1 asset the manifest lists, each under its content hash
    And the uploadable component should have a stored file with the same content as "image.png"
    When the site is generated as fixtures again into the same asset store
    Then the generated fixtures, their asset manifest and the asset store should be identical to the previous generation

  Scenario: A configured local folder reloads as the same site and a second generation changes nothing
    Given fixture assets are configured to be stored in a local folder
    And the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures and reloaded
    Then the generated fixtures should contain "$cwa->asset('"
    And the fixture asset store should hold exactly the 1 asset the manifest lists, each under its content hash
    And the uploadable component should have a stored file with the same content as "image.png"
    When the site is generated as fixtures again into the same asset store
    Then the generated fixtures, their asset manifest and the asset store should be identical to the previous generation

  Scenario: A configured flysystem adapter reloads as the same site and a second generation changes nothing
    Given fixture assets are configured to be stored on the "public_url_local" filesystem under a prefix
    And the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures and reloaded
    Then the generated fixtures should contain "$cwa->asset('"
    And the fixture asset store should hold exactly the 1 asset the manifest lists, each under its content hash
    And the uploadable component should have a stored file with the same content as "image.png"
    And the command output should contain "silverback:api-components:prune-fixture-assets"
    When the site is generated as fixtures again into the same asset store
    Then the generated fixtures, their asset manifest and the asset store should be identical to the previous generation

  Scenario: doctrine:fixtures:load reads a scaffold's assets through its manifest and stores each under its manifest name
    When I run the command "doctrine:fixtures:load --append --group=cwa_hashed_asset"
    Then the uploadable component should have a stored file with the same content as "image.png"
    And the uploadable component's stored file should be named "home-hero" with a token and the extension "png"

  Scenario: An upload stored under a PHP temp name keeps the name its reload is stored under
    Given the site has an uploadable component whose file is stored as "components/phpGPCPOl" with the content of "image.png"
    When the site is generated as fixtures with the option "--assets-store" set to "local" and reloaded
    Then the fixture asset manifest should name the asset "phpgpcpol.png"
    When the site is generated as fixtures again into the same asset store
    Then the generated fixtures, their asset manifest and the asset store should be identical to the previous generation

  Scenario: The same file uploaded twice is stored once and both uploads come back
    Given the site has 2 uploadable components with the file "image.png"
    When the site is generated as fixtures with the option "--assets-store" set to "local" and reloaded
    Then the fixture asset store should hold exactly the 1 asset the manifest lists, each under its content hash
    And every uploadable component should have a stored file with the same content as "image.png"

  Scenario: Two scaffolds sharing a local folder keep each other's assets when either is generated
    Given fixture assets are configured to be stored in a local folder
    And the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures to the file "FirstScaffold.php"
    And the uploadable component's file is replaced with "test_file.txt"
    And the site is generated as fixtures to the file "SecondScaffold.php"
    And the database is purged and the generated fixtures "FirstScaffold.php" are loaded
    Then the uploadable component should have a stored file with the same content as "image.png"
    When the database is purged and the generated fixtures "SecondScaffold.php" are loaded
    Then the uploadable component should have a stored file with the same content as "test_file.txt"

  Scenario: An asset one scaffold no longer uses stays in a local folder while another scaffold there lists it
    Given fixture assets are configured to be stored in a local folder
    And the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures to the file "FirstScaffold.php"
    And the site is generated as fixtures to the file "SecondScaffold.php"
    And the uploadable component's file is replaced with "test_file.txt"
    And the site is generated as fixtures to the file "FirstScaffold.php"
    Then the command output should not contain "deleted from the fixture asset store"
    When the database is purged and the generated fixtures "SecondScaffold.php" are loaded
    Then the uploadable component should have a stored file with the same content as "image.png"
    When the database is purged and the generated fixtures "FirstScaffold.php" are loaded
    Then the uploadable component should have a stored file with the same content as "test_file.txt"

  Scenario: An asset the same scaffold no longer uses is deleted from its local folder
    Given fixture assets are configured to be stored in a local folder
    And the site has an uploadable component with the file "image.png"
    When the site is generated as fixtures
    And the uploadable component's file is replaced with "test_file.txt"
    And the site is generated as fixtures
    Then the fixture asset store should hold exactly the 1 asset the manifest lists, each under its content hash
    And the command output should contain "1 asset(s) no longer in the manifest deleted from the fixture asset store"
    When the database is purged and the generated fixtures are loaded
    Then the uploadable component should have a stored file with the same content as "test_file.txt"
