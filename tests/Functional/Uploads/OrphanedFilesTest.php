<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Uploads;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithConstraints;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithPublishedConstraints;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Symfony\Component\Console\Output\OutputInterface;

final class OrphanedFilesTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ConsoleTrait;
    use UploadsAreaTrait;

    private const string TOO_MANY_PIXELS = 'The image has too many pixels (357500 pixels). Maximum amount expected is 300000 pixels.';

    public function test_a_stored_file_no_row_references_is_reported_on_every_uploadable_filesystem_that_holds_it_and_the_imagine_cache_is_not_scanned(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('orphan-0a1b2c3d.png', true);
        self::writeStoredFile('components/nested/notes-1234abcd.txt', true);
        self::writeStoredFile('cache/thumbnail/orphan-0a1b2c3d.png', true);
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNow('generatedAt', $now);
        self::assertJsonNodeListsExactlyTheFiles('orphanedFiles', ['local:components/nested/notes-1234abcd.txt', 'local:orphan-0a1b2c3d.png', 'public_url_local:components/nested/notes-1234abcd.txt', 'public_url_local:orphan-0a1b2c3d.png']);
        self::assertJsonNodeCount('missingFiles', 0);
        self::assertJsonNodeCount('unknownFiles', 0);
    }

    public function test_a_file_a_row_references_is_not_reported_including_one_only_a_draft_references(): void
    {
        $client = self::adminClient();
        $referenced = self::createUploadable(new DummyUploadable());
        self::ageStoredFile(self::storedPathOf($referenced));
        $draft = self::createPublishableUploadable(false);
        self::ageStoredFile(self::storedPathOf($draft));
        self::writeStoredFile('orphan-0a1b2c3d.png', true);

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactlyTheFiles('orphanedFiles', ['local:orphan-0a1b2c3d.png', 'public_url_local:orphan-0a1b2c3d.png']);
        self::assertJsonNodeCount('missingFiles', 0);
    }

    public function test_an_unreferenced_file_the_bundle_did_not_name_and_never_served_is_reported_as_unknown_not_orphaned(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('logo.png', true);
        self::writeStoredFile('served-before-tokens.png', true);
        self::addFileInfo('served-before-tokens.png');
        self::writeStoredFile('tokenised-0a1b2c3d.png', true);

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactlyTheFiles('orphanedFiles', ['local:served-before-tokens.png', 'local:tokenised-0a1b2c3d.png', 'public_url_local:served-before-tokens.png', 'public_url_local:tokenised-0a1b2c3d.png']);
        self::assertJsonNodeListsExactlyTheFiles('unknownFiles', ['local:logo.png', 'public_url_local:logo.png']);
    }

    public function test_an_unknown_file_is_never_deleted_all_leaves_it_in_place_and_naming_it_is_rejected(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('logo.png', true);
        self::writeStoredFile('tokenised-0a1b2c3d.png', true);

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactlyTheFiles('deleted', ['local:tokenised-0a1b2c3d.png', 'public_url_local:tokenised-0a1b2c3d.png']);
        self::assertStoredFileExists('logo.png');
        self::assertStoredFileNotExists('tokenised-0a1b2c3d.png');

        self::requestOrphanedFilesDeletion($client, ['logo.png']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('deleted', 0);
        self::assertJsonNodeSame('rejected[0].path', 'logo.png');
        self::assertJsonNodeSame('rejected[0].reason', 'unknown');
        self::assertStoredFileExists('logo.png');
    }

    public function test_a_file_younger_than_the_minimum_age_is_not_reported_because_its_row_may_not_be_flushed_yet(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('uploading.png');

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('orphanedFiles', 0);
    }

    public function test_a_row_whose_stored_file_is_missing_is_reported_as_missing_and_never_as_orphaned(): void
    {
        $client = self::adminClient();
        $broken = self::createUploadable(new DummyUploadable());
        self::removeStoredFilesOf($broken);

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('orphanedFiles', 0);
        self::assertJsonNodeCount('missingFiles', 1);
        self::assertJsonNodeSame('missingFiles[0].resource', self::iriOf($broken));
        self::assertJsonNodeSame('missingFiles[0].adapter', 'local');
        self::assertJsonNodeSame('missingFiles[0].path', self::storedPathOf($broken));
    }

    public function test_the_report_is_404_until_a_scan_has_stored_one(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_the_report_is_never_stored_by_a_shared_cache_because_a_later_scan_is_not_a_write_that_could_purge_it(): void
    {
        $client = self::adminClient();
        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(200);
        $cacheControl = (string) $client->getResponse()->getHeaders(false)['cache-control'][0];
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }

    public function test_a_user_without_the_administrator_role_cannot_request_a_scan(): void
    {
        $client = self::userClient();

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');

        self::assertResponseStatusCodeSame(403);
        self::assertNull(self::orphanedFileReportStore()->fetch());
    }

    public function test_a_user_without_the_administrator_role_cannot_fetch_the_report(): void
    {
        $client = self::userClient();
        self::storeAnOrphanedFilesReport();

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_user_without_the_administrator_role_cannot_delete_orphaned_files(): void
    {
        $client = self::userClient();
        self::writeStoredFile('orphan-0a1b2c3d.png', true);

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(403);
        self::assertStoredFileExists('orphan-0a1b2c3d.png');
    }

    public function test_an_anonymous_request_for_the_report_is_refused_by_the_operations_own_security(): void
    {
        $client = static::createClient();
        self::emptyUploadFilestores();
        self::storeAnOrphanedFilesReport();

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(401);
    }

    public function test_deleting_selected_paths_deletes_only_those_and_the_stored_report_no_longer_lists_them(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('first-00000001.png', true);
        self::writeStoredFile('second-00000002.png', true);

        self::requestOrphanedFilesDeletion($client, ['first-00000001.png']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactlyTheFiles('deleted', ['local:first-00000001.png', 'public_url_local:first-00000001.png']);
        self::assertJsonNodeCount('rejected', 0);
        self::assertStoredFileNotExists('first-00000001.png');
        self::assertStoredFileExists('second-00000002.png');

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertJsonNodeListsExactlyTheFiles('orphanedFiles', ['local:second-00000002.png', 'public_url_local:second-00000002.png']);
    }

    public function test_deleting_all_orphaned_files_deletes_everything_a_fresh_scan_reports_and_nothing_else(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('first-00000001.png', true);
        self::writeStoredFile('components/second-00000002.png', true);
        self::writeStoredFile('uploading.png');
        $referenced = self::createUploadable(new DummyUploadable());
        self::ageStoredFile(self::storedPathOf($referenced));

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactlyTheFiles('deleted', ['local:first-00000001.png', 'local:components/second-00000002.png', 'public_url_local:first-00000001.png', 'public_url_local:components/second-00000002.png']);
        self::assertJsonNodeCount('rejected', 0);
        self::assertStoredFileNotExists('first-00000001.png');
        self::assertStoredFileNotExists('components/second-00000002.png');
        self::assertStoredFileExists('uploading.png');
        self::assertStoredFilesOfResourceExist($referenced);
    }

    public function test_a_path_that_stopped_being_orphaned_between_the_scan_and_the_delete_is_rejected_and_kept(): void
    {
        $client = self::adminClient();
        self::writeStoredFile('claimed-0000000c.txt', true);
        $claimant = self::createUploadable(new DummyUploadable());
        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');
        self::setStoredFilename($claimant, 'claimed-0000000c.txt');

        self::requestOrphanedFilesDeletion($client, ['claimed-0000000c.txt']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('deleted', 0);
        self::assertJsonNodeSame('rejected[0].path', 'claimed-0000000c.txt');
        self::assertJsonNodeSame('rejected[0].reason', 'not_orphaned');
        self::assertStoredFileExists('claimed-0000000c.txt');
    }

    public function test_a_missing_file_entry_cannot_be_deleted_and_a_path_that_does_not_exist_is_rejected_cleanly(): void
    {
        $client = self::adminClient();
        $broken = self::createUploadable(new DummyUploadable());
        self::removeStoredFilesOf($broken);
        $path = self::storedPathOf($broken);

        self::requestOrphanedFilesDeletion($client, [$path]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('deleted', 0);
        self::assertJsonNodeSame('rejected[0].path', $path);
        self::assertJsonNodeSame('rejected[0].reason', 'not_found');
        self::assertSame($path, self::storedPathOf($broken));
    }

    public static function invalidDeletionBodies(): iterable
    {
        yield 'neither paths nor all' => ['{}'];
        yield 'all false' => ['{"all": false}'];
        yield 'both all and paths' => ['{"all": true, "paths": ["first-00000001.png"]}'];
    }

    #[DataProvider('invalidDeletionBodies')]
    public function test_a_deletion_request_names_paths_or_all_never_both_and_never_neither(string $body): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', $body);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_the_scan_command_prints_the_counts_lists_the_files_with_verbose_stores_the_report_and_deletes_nothing(): void
    {
        static::bootKernel();
        self::emptyUploadFilestores();
        self::writeStoredFile('orphan-0a1b2c3d.png', true);
        $broken = self::createUploadable(new DummyUploadable());
        self::removeStoredFilesOf($broken);

        $tester = self::runConsoleCommand('silverback:api-components:scan-orphaned-files', [], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('Orphaned files: 2', $output);
        self::assertStringContainsString('  local: orphan-0a1b2c3d.png', $output);
        self::assertStringContainsString('  public_url_local: orphan-0a1b2c3d.png', $output);
        self::assertStringContainsString('Unknown files: 0', $output);
        self::assertStringContainsString('Missing files: 1', $output);
        self::assertStoredFileExists('orphan-0a1b2c3d.png');
        self::assertStoredReportLists(2, 1, 0);
    }

    public function test_a_referenced_file_that_breaks_its_fields_current_constraints_is_reported_as_invalid_with_every_violation_and_nothing_deletes_it(): void
    {
        $client = self::adminClient();
        $tooManyPixels = self::createUploadable(new DummyUploadableWithConstraints());
        $wrongType = self::createUploadable(new DummyUploadableWithConstraints(), ['file' => 'test_file.txt']);
        self::createUploadable(new DummyUploadableWithConstraints(), ['file' => 'image.svg']);
        self::createUploadable(new DummyUploadable());

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('orphanedFiles', 0);
        self::assertJsonNodeCount('missingFiles', 0);
        self::assertJsonNodeCount('invalidFiles', 2);
        self::assertJsonNodeSame('invalidFiles[0].resource', self::iriOf($tooManyPixels));
        self::assertJsonNodeSame('invalidFiles[0].field', 'file');
        self::assertJsonNodeSame('invalidFiles[0].adapter', 'local');
        self::assertJsonNodeSame('invalidFiles[0].path', self::storedPathOf($tooManyPixels));
        self::assertJsonNodeCount('invalidFiles[0].violations', 1);
        self::assertJsonNodeSame('invalidFiles[0].violations[0]', self::TOO_MANY_PIXELS);
        self::assertJsonNodeSame('invalidFiles[1].resource', self::iriOf($wrongType));
        self::assertJsonNodeCount('invalidFiles[1].violations', 2);
        self::assertStringContainsString('The mime type of the file is invalid', self::jsonNode('invalidFiles[1].violations[0]'));
        self::assertJsonNodeSame('invalidFiles[1].violations[1]', 'This file is not a valid image.');

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('deleted', 0);
        self::assertStoredFileExists(self::storedPathOf($tooManyPixels));
        self::assertStoredFileExists(self::storedPathOf($wrongType));

        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertJsonNodeCount('invalidFiles', 2);
    }

    public function test_a_published_rows_file_is_also_checked_against_its_fields_publish_group_constraints_and_a_drafts_only_against_the_default_group(): void
    {
        $client = self::adminClient();
        $published = new DummyUploadableWithPublishedConstraints();
        $published->setPublishedAt(new \DateTime('-1 day'));
        self::createUploadable($published);
        $draft = new DummyUploadableWithPublishedConstraints();
        $draft->setPublishedAt(null);
        self::createUploadable($draft);

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('invalidFiles', 1);
        self::assertJsonNodeSame('invalidFiles[0].resource', self::iriOf($published));
        self::assertJsonNodeCount('invalidFiles[0].violations', 1);
        self::assertJsonNodeSame('invalidFiles[0].violations[0]', self::TOO_MANY_PIXELS);
    }

    public function test_the_file_info_cached_when_the_file_was_served_is_trusted_over_reading_the_stored_object(): void
    {
        $client = self::adminClient();
        $cached = self::createUploadable(new DummyUploadableWithConstraints());
        self::addFileInfo(self::storedPathOf($cached), 'image/png', 1, 100, 100);

        self::scanAndFetchReport($client);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('invalidFiles', 0);
    }

    public function test_the_scan_command_counts_invalid_files_and_lists_each_with_its_violations_with_verbose_and_modifies_nothing(): void
    {
        static::bootKernel();
        self::emptyUploadFilestores();
        $tooManyPixels = self::createUploadable(new DummyUploadableWithConstraints());

        $tester = self::runConsoleCommand('silverback:api-components:scan-orphaned-files', [], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Invalid files: 1', $tester->getDisplay());
        self::assertStringContainsString('    ' . self::TOO_MANY_PIXELS, $tester->getDisplay());
        self::assertStoredFileExists(self::storedPathOf($tooManyPixels));
        self::assertStoredReportLists(0, 0, 1);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::emptyUploadFilestores();

        return $client;
    }

    private static function userClient(): Client
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::emptyUploadFilestores();

        return $client;
    }

    private static function scanAndFetchReport(Client $client): void
    {
        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');
        self::requestJsonLd($client, 'GET', '/_/orphaned_files');
    }

    private static function storeAnOrphanedFilesReport(): void
    {
        self::orphanedFileReportStore()->save(new OrphanedFileReport(new \DateTimeImmutable()));
        static::rebootKernel();
    }

    private static function assertStoredReportLists(int $orphaned, int $missing, int $invalid): void
    {
        $report = self::orphanedFileReportStore()->fetch();
        self::assertNotNull($report);
        self::assertCount($orphaned, $report->orphanedFiles);
        self::assertCount($missing, $report->missingFiles);
        self::assertCount($invalid, $report->invalidFiles);
    }
}
