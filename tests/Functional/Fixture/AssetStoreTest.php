<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class AssetStoreTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;

    public function test_with_no_asset_store_configured_files_are_still_written_beside_the_scaffold_under_readable_names(): void
    {
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSite();

        $this->assertGeneratedFixturesContain("new File(__DIR__ . '/assets/");
        $this->assertGeneratedFixturesNotContain('$cwa->asset(');
        $this->assertNoAssetManifestWritten();
    }

    public function test_the_local_store_beside_the_scaffold_reloads_as_the_same_site_and_a_second_generation_changes_nothing(): void
    {
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSiteAndReload(options: ['--assets-store' => 'local']);

        $this->assertGeneratedFixturesContain("\$cwa->asset('");
        $this->assertGeneratedFixturesNotContain('new File(');
        $this->assertAssetStoreHoldsExactlyTheManifestAssets(1);
        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->generateSiteAgainIntoTheSameAssetStore();

        $this->assertGenerationManifestAndAssetStoreIdenticalToThePreviousOne();
    }

    public function test_a_configured_local_folder_reloads_as_the_same_site_and_a_second_generation_changes_nothing(): void
    {
        $this->configureLocalAssetStore();
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSiteAndReload();

        $this->assertGeneratedFixturesContain("\$cwa->asset('");
        $this->assertAssetStoreHoldsExactlyTheManifestAssets(1);
        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->generateSiteAgainIntoTheSameAssetStore();

        $this->assertGenerationManifestAndAssetStoreIdenticalToThePreviousOne();
    }

    public function test_a_configured_flysystem_adapter_reloads_as_the_same_site_and_a_second_generation_changes_nothing(): void
    {
        $this->configureFlysystemAssetStore('public_url_local');
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSiteAndReload();

        $this->assertGeneratedFixturesContain("\$cwa->asset('");
        $this->assertAssetStoreHoldsExactlyTheManifestAssets(1);
        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');
        $this->assertCommandOutputContains('silverback:api-components:prune-fixture-assets');

        $this->generateSiteAgainIntoTheSameAssetStore();

        $this->assertGenerationManifestAndAssetStoreIdenticalToThePreviousOne();
    }

    public function test_doctrine_fixtures_load_reads_a_scaffolds_assets_through_its_manifest_and_stores_each_under_its_manifest_name(): void
    {
        $this->runCommandLine('doctrine:fixtures:load', 'cwa_hashed_asset');

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');
        $this->assertUploadableComponentsStoredFileIsNamed('home-hero', 'png');
    }

    public function test_an_upload_stored_under_a_php_temp_name_keeps_the_name_its_reload_is_stored_under(): void
    {
        $this->siteHasAnUploadableComponentWhoseFileIsStoredAs('components/phpGPCPOl', 'image.png');
        $this->generateSiteAndReload(options: ['--assets-store' => 'local']);

        $this->assertAssetManifestNamesTheAsset('phpgpcpol.png');

        $this->generateSiteAgainIntoTheSameAssetStore();

        $this->assertGenerationManifestAndAssetStoreIdenticalToThePreviousOne();
    }

    public function test_the_same_file_uploaded_twice_is_stored_once_and_both_uploads_come_back(): void
    {
        $this->siteHasUploadableComponentsWithTheFile(2, 'image.png');
        $this->generateSiteAndReload(options: ['--assets-store' => 'local']);

        $this->assertAssetStoreHoldsExactlyTheManifestAssets(1);
        $this->assertEveryUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');
    }

    public function test_two_scaffolds_sharing_a_local_folder_keep_each_others_assets_when_either_is_generated(): void
    {
        $this->configureLocalAssetStore();
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSite('FirstScaffold.php');
        $this->replaceUploadableComponentFile('test_file.txt');
        $this->generateSite('SecondScaffold.php');
        $this->purgeAndLoadGeneratedFixtures('FirstScaffold.php');

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->purgeAndLoadGeneratedFixtures('SecondScaffold.php');

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('test_file.txt');
    }

    public function test_an_asset_one_scaffold_no_longer_uses_stays_in_a_local_folder_while_another_scaffold_there_lists_it(): void
    {
        $this->configureLocalAssetStore();
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSite('FirstScaffold.php');
        $this->generateSite('SecondScaffold.php');
        $this->replaceUploadableComponentFile('test_file.txt');
        $this->generateSite('FirstScaffold.php');

        $this->assertCommandOutputNotContains('deleted from the fixture asset store');

        $this->purgeAndLoadGeneratedFixtures('SecondScaffold.php');

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->purgeAndLoadGeneratedFixtures('FirstScaffold.php');

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('test_file.txt');
    }

    public function test_an_asset_the_same_scaffold_no_longer_uses_is_deleted_from_its_local_folder(): void
    {
        $this->configureLocalAssetStore();
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSite();
        $this->replaceUploadableComponentFile('test_file.txt');
        $this->generateSite();

        $this->assertAssetStoreHoldsExactlyTheManifestAssets(1);
        $this->assertCommandOutputContains('1 asset(s) no longer in the manifest deleted from the fixture asset store');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('test_file.txt');
    }
}
