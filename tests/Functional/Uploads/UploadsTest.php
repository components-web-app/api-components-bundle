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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\RejectedDeleteMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadablePublicUrl;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublish;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublishCustomGroup;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableTemporaryUrl;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\MercureTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;

final class UploadsTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ConsoleTrait;
    use MercureTrait;
    use PublishableTrait;
    use UploadsAreaTrait;

    private const string STORED_SVG = '#^components/image-[0-9a-f]{8}\.svg$#';

    private const string STORED_PNG = '#^components/image-[0-9a-f]{8}\.png$#';

    public static function base64Files(): iterable
    {
        yield 'an SVG' => ['image.svg'];
        yield 'a PNG' => ['image.png'];
        yield 'a text file' => ['test_file.txt'];
        yield 'a Word document' => ['test_file.docx'];
    }

    #[DataProvider('base64Files')]
    public function test_i_can_create_a_new_dummy_files_component_with_a_json_base64_data_uri(string $file): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_uploadables', ['file' => self::dataUriOf($file)]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeNotExists('filePath');
    }

    public function test_a_base64_upload_is_checked_against_its_fields_file_constraints_not_refused_as_a_failed_upload(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_uploadable_with_constraints', ['file' => self::dataUriOf('image.png')]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'file');
        self::assertJsonNodeSame('violations[0].message', 'The image has too many pixels (357500 pixels). Maximum amount expected is 300000 pixels.');
    }

    public function test_a_base64_upload_that_meets_its_fields_file_constraints_is_created(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_uploadable_with_constraints', ['file' => self::dataUriOf('image.svg')]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeNotExists('filePath');
    }

    public function test_i_can_create_a_new_dummy_files_component_with_base64_data_that_is_just_a_string(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_uploadables', ['file' => self::base64Of('image.svg')]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeNotExists('filePath');
    }

    #[DataProvider('base64Files')]
    public function test_i_can_create_a_new_dummy_files_component_with_a_multipart_form_data_request(string $file): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_uploadables/upload', ['file' => $file]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeNotExists('filePath');
    }

    public function test_i_get_an_error_if_i_send_a_json_request_to_the_multipart_form_data_endpoint(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/dummy_uploadables/upload', '{}');

        self::assertResponseStatusCodeSame(415);
    }

    public function test_i_can_get_an_image_media_resource_with_imagine_filters_configured(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('uploadable_has_files_with_imagine.schema.json');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].contentUrl', self::downloadLinkOf($resource));
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].@type', 'http://schema.org/MediaObject');
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[0].imagineFilter');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[1].imagineFilter', 'thumbnail');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[1].width', 350);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[1].height', 500);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[1].mimeType', 'image/png');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[2].imagineFilter', 'square_thumbnail');
    }

    public function test_i_get_the_endpoint_of_the_default_media_object_inline(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'GET', self::iriOf($resource) . '/download/file');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'image/png');
        self::assertStringContainsString('inline; filename=image-', (string) $client->getResponse()->getHeaders(false)['content-disposition'][0]);
    }

    public function test_i_get_the_endpoint_of_the_default_media_object_as_an_attachment_with_download_true(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'GET', self::iriOf($resource) . '/download/file?download=true');

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type', 'image/png');
        self::assertStringContainsString('attachment; filename=image-', (string) $client->getResponse()->getHeaders(false)['content-disposition'][0]);
    }

    public function test_i_can_update_a_media_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'PATCH', self::iriOf($resource), ['file' => self::dataUriOf('image.png')]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeNotExists('filePath');
    }

    public function test_when_an_uploadable_resource_is_also_publishable_uploading_a_resource_creates_a_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.png']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeSame('_metadata.publishable.published', false);
    }

    public function test_when_i_publish_an_uploadable_component_the_file_should_still_exist_and_media_object_returned(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishableUploadable(false);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertJsonNodeSame('_metadata.publishable.published', true);
    }

    public function test_when_we_create_a_draft_through_an_upload_to_the_published_and_then_publish_the_draft_the_new_published_resource_should_still_have_a_file(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $published = self::createPublishableUploadable();

        $this->uploadMultipart($client, self::iriOf($published) . '/upload', ['file' => 'image.svg']);
        self::assertResponseStatusCodeSame(201);
        $draftIri = self::jsonNode('@id');
        self::requestJsonLd($client, 'PATCH', $draftIri, '{"publishedAt": "1970-11-11T23:59:59+00:00"}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertJsonMatchesSchemaFile('uploadable_has_files_with_imagine.schema.json');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].contentUrl', self::downloadLinkOf($published));
        self::assertMatchesRegularExpression(self::STORED_SVG, self::storedPathOf($published));
        self::assertStoredFilesOfResourceExist($published);
    }

    public function test_i_can_set_the_file_to_null_to_delete_it_in_a_publishable_component_this_creates_a_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();

        self::requestJsonLd($client, 'PATCH', self::iriOf($resource), ['file' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('uploadable_no_files.schema.json');
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertHasStoredFile($resource);
    }

    public function test_i_can_set_the_file_to_null_to_delete_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'PATCH', self::iriOf($resource), ['file' => null]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('uploadable_no_files.schema.json');
        $reloaded = self::findByIri(self::iriOf($resource));
        self::assertInstanceOf(DummyUploadableWithImagineFilters::class, $reloaded);
        self::assertNull($reloaded->getFilename());
    }

    public function test_i_can_delete_a_media_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'DELETE', self::iriOf($resource));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_deleting_an_uploadable_resource_deletes_its_stored_file(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadable());
        $files = self::assertStoredFilesOfResourceExist($resource);

        self::requestJsonLd($client, 'DELETE', self::iriOf($resource));

        self::assertResponseStatusCodeSame(204);
        self::assertStoredFilesRemoved($files);
    }

    public function test_a_delete_the_database_rejects_leaves_the_stored_file_in_place(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadable());
        $files = self::storedFilesOf($resource);
        RejectedDeleteMiddleware::setRejectDeletes(true);

        self::requestJsonLd($client, 'DELETE', self::iriOf($resource));

        self::assertResponseStatusCodeSame(500);
        RejectedDeleteMiddleware::setRejectDeletes(false);
        self::assertSame($files, self::assertStoredFilesOfResourceExist($resource));
    }

    public function test_deleting_the_group_holding_an_uploadable_component_deletes_the_components_stored_file(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();
        $position = self::placeInComponentPosition($resource);
        $files = self::assertStoredFilesOfResourceExist($resource);

        self::requestJsonLd($client, 'DELETE', self::iriOf($position->componentGroup));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists(self::iriOf($resource));
        self::assertStoredFilesRemoved($files);
    }

    public function test_deleting_every_orphan_deletes_the_stored_file_of_an_orphaned_uploadable_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();
        $iri = self::iriOf($resource);
        $files = self::assertStoredFilesOfResourceExist($resource);

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertResourceNotExists($iri);
        self::assertStoredFilesRemoved($files);
    }

    public function test_deleting_a_draft_keeps_the_stored_file_its_published_version_shares(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createUploadablePublishedWithDraft();
        self::setStoredFilename($draft, self::storedPathOf($published));
        $draftIri = self::iriOf($draft);

        self::requestJsonLd($client, 'DELETE', $draftIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($draftIri);
        self::assertStoredFilesOfResourceExist($published);
    }

    public function test_deleting_a_published_resource_keeps_the_stored_file_its_draft_shares(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createUploadablePublishedWithDraft();
        self::setStoredFilename($draft, self::storedPathOf($published));
        $publishedIri = self::iriOf($published);

        self::requestJsonLd($client, 'DELETE', $publishedIri . '?published=true');

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($publishedIri);
        self::assertStoredFilesOfResourceExist($draft);
    }

    public function test_when_i_publish_a_draft_image_where_a_published_image_exists_the_component_positions_should_be_present_on_the_newly_published_resource(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createUploadablePublishedWithDraft();
        self::placeInComponentPosition($published);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => '1970-11-11T23:59:59+00:00']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('componentPositions[0]');
        self::assertMatchesRegularExpression(self::STORED_PNG, self::storedPathOf($published));
        self::assertStoredFilesOfResourceExist($published);
    }

    public function test_when_i_upload_a_new_file_to_a_publishable_uploadable_the_new_draft_should_not_have_a_component_position_and_the_original_image_should_still_exist(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();
        self::setStoredFilename($resource, 'existing.png');
        self::placeInComponentPosition($resource);

        self::requestJsonLd($client, 'PATCH', self::iriOf($resource), ['file' => self::dataUriOf('image.png')]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('componentPositions[0]');
        $reloaded = self::findByIri(self::iriOf($resource));
        self::assertInstanceOf(DummyUploadableAndPublishable::class, $reloaded);
        self::assertCount(1, $reloaded->getComponentPositions());
    }

    public function test_when_i_upload_an_image_to_a_published_resource_to_create_a_draft_the_published_image_data_and_filename_should_remain_intact(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $resource = self::createPublishableUploadable();

        $this->uploadMultipart($client, self::iriOf($resource) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('_metadata.publishable.published', false);
        self::assertJsonMatchesSchemaFile('uploadable_has_files.schema.json');
        self::assertHasStoredFile($resource);
    }

    public function test_when_i_upload_to_overwrite_a_draft_cache_should_overwrite_properly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [, $draft] = self::createUploadablePublishedWithDraft();

        $this->uploadMultipart($client, self::iriOf($draft) . '/upload', ['file' => 'image.svg']);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_when_i_upload_a_file_to_an_existing_draft_and_then_publish_it_the_published_resource_keeps_the_uploaded_file(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createUploadablePublishedWithDraft();

        $this->uploadMultipart($client, self::iriOf($draft) . '/upload', ['file' => 'image.svg']);
        self::assertResponseStatusCodeSame(201);
        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => '1970-11-11T23:59:59+00:00']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertMatchesRegularExpression(self::STORED_SVG, self::storedPathOf($published));
        self::assertStoredFilesOfResourceExist($published);
    }

    public function test_publishing_a_draft_that_shares_the_published_resources_stored_file_keeps_that_file(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createUploadablePublishedWithDraft();
        self::setStoredFilename($draft, self::storedPathOf($published));

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => '1970-11-11T23:59:59+00:00']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('@id', self::iriOf($published));
        self::assertMatchesRegularExpression(self::STORED_PNG, self::storedPathOf($published));
        self::assertStoredFilesOfResourceExist($published);
    }

    public function test_when_i_upload_a_file_to_a_draft_that_has_never_been_published_and_then_publish_it_the_file_is_kept(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = self::createPublishableUploadable(false);

        $this->uploadMultipart($client, self::iriOf($draft) . '/upload', ['file' => 'image.svg']);
        self::assertResponseStatusCodeSame(201);
        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => '1970-11-11T23:59:59+00:00']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
        self::assertMatchesRegularExpression(self::STORED_SVG, self::storedPathOf($draft));
        self::assertStoredFilesOfResourceExist($draft);
    }

    public function test_an_uploadable_field_configured_with_url_generator_public_returns_a_direct_public_url(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadablePublicUrl());

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertStringStartsWith('http://localhost/uploads/', self::jsonNode('_metadata.mediaObjects.file[0].contentUrl'));
    }

    public function test_an_uploadable_field_configured_with_url_generator_temporary_falls_back_to_the_api_download_endpoint_when_the_adapter_does_not_support_temporary_urls(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableTemporaryUrl());

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].contentUrl', self::downloadLinkOf($resource));
    }

    public function test_an_imagine_filter_with_an_output_format_stores_its_variant_with_that_formats_extension(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].imagineFilter', 'webp_thumbnail');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].mimeType', 'image/webp');
        self::assertJsonNodeMatches('_metadata.mediaObjects.file[3].contentUrl', '#/media/cache/webp_thumbnail/image-[0-9a-f]{8}\.webp$#');
        self::assertStoredFileAtUrlIs(self::jsonNode('_metadata.mediaObjects.file[3].contentUrl'), 'in_memory', 'image/webp');
        self::assertJsonNodeMatches('_metadata.mediaObjects.file[1].contentUrl', '#/media/cache/thumbnail/image-[0-9a-f]{8}\.png$#');
    }

    public function test_a_variant_stored_again_replaces_the_file_info_recorded_for_its_filter(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());
        self::addFileInfo(self::storedPathOf($resource), 'image/png', 4690000, 350, 500, 'webp_thumbnail');

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].imagineFilter', 'webp_thumbnail');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].mimeType', 'image/webp');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].width', 70);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[3].height', 100);
        self::assertSame(1, self::countFileInfoRows('webp_thumbnail'));
    }

    public function test_removing_an_imagine_filters_cache_from_the_console_removes_its_file_info_rows_for_every_path_and_keeps_the_rest(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());
        self::requestJsonLd($client, 'GET', self::iriOf($resource));
        self::assertSame(1, self::countFileInfoRows('thumbnail'));

        $tester = self::runApplication(['command' => 'liip:imagine:cache:remove', '--filter' => ['thumbnail']]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(0, self::countFileInfoRows('thumbnail'));
        self::assertSame(1, self::countFileInfoRows('square_thumbnail'));
        self::assertSame(1, self::countFileInfoRows());
    }

    public function test_removing_every_imagine_cache_from_the_console_removes_every_variants_file_info_row_and_keeps_the_originals(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());
        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        $tester = self::runApplication(['command' => 'liip:imagine:cache:remove']);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(0, self::countFileInfoRows('thumbnail'));
        self::assertSame(0, self::countFileInfoRows('square_thumbnail'));
        self::assertSame(0, self::countFileInfoRows('webp_thumbnail'));
        self::assertSame(1, self::countFileInfoRows());
    }

    public function test_uploading_a_non_image_to_an_imagine_filtered_field_does_not_attempt_image_processing(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_multiple_uploadables/upload', ['preview' => 'test_file.docx']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeNotExists('_metadata.mediaObjects.preview[0].imagineFilter');
        self::assertJsonNodeNotExists('_metadata.mediaObjects.preview[1]');
    }

    public function test_uploading_an_image_to_an_imagine_filtered_field_still_produces_the_imagine_variant(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_multiple_uploadables/upload', ['preview' => 'image.png']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeNotExists('_metadata.mediaObjects.preview[0].imagineFilter');
        self::assertJsonNodeSame('_metadata.mediaObjects.preview[1].imagineFilter', 'thumbnail');
    }

    public function test_uploading_to_one_uploadable_field_does_not_populate_the_other_field(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_multiple_uploadables/upload', ['file' => 'image.png']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeExists('_metadata.mediaObjects.file[0]');
        self::assertJsonNodeNotExists('_metadata.mediaObjects.preview');
    }

    public function test_publishing_is_rejected_per_field_when_a_required_on_publish_file_is_missing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyUploadableRequiredOnPublish();
        $draft->setPublishedAt(null);
        self::persistAndFlush($draft);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 2);
        self::assertJsonNodeSame('violations[0].propertyPath', 'file');
        self::assertJsonNodeSame('violations[0].message', 'You must upload a file before publishing.');
        self::assertJsonNodeSame('violations[1].propertyPath', 'preview');
        self::assertJsonNodeSame('violations[1].message', 'A file must be uploaded for the `preview` field before publishing.');
    }

    public function test_publishing_is_still_rejected_when_only_some_required_on_publish_files_are_present(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyUploadableRequiredOnPublish();
        $draft->setPublishedAt(null);
        self::createUploadable($draft);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'preview');
    }

    public function test_publishing_succeeds_when_all_required_on_publish_files_are_present(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyUploadableRequiredOnPublish();
        $draft->setPublishedAt(null);
        self::createUploadable($draft, ['file' => 'image.png', 'preview' => 'image.png']);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
    }

    public function test_required_on_publish_follows_custom_publishable_validation_groups(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyUploadableRequiredOnPublishCustomGroup();
        $draft->setPublishedAt(null);
        self::persistAndFlush($draft);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeCount('violations', 1);
        self::assertJsonNodeSame('violations[0].propertyPath', 'file');
    }

    public function test_publishing_with_custom_publishable_validation_groups_succeeds_when_the_required_file_is_present(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $draft = new DummyUploadableRequiredOnPublishCustomGroup();
        $draft->setPublishedAt(null);
        self::createUploadable($draft);

        self::requestJsonLd($client, 'PATCH', self::iriOf($draft), ['publishedAt' => gmdate('Y-m-d\TH:i:s+00:00')]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.publishable.published', true);
    }

    public function test_a_multipart_file_upload_fires_exactly_one_mercure_notification(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_uploadables/upload', ['file' => 'image.png']);

        self::assertResponseStatusCodeSame(201);
        self::assertCount(1, self::mercureUpdatesInLastRequest());
    }

    public function test_uploading_keeps_the_original_filename_with_a_unique_token_and_never_collides_with_another_resources_file(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_uploadables/upload', ['file' => 'image.png']);

        self::assertResponseStatusCodeSame(201);
        $first = self::findByIri(self::jsonNode('@id'));
        self::assertInstanceOf(DummyUploadable::class, $first);
        $second = self::createUploadable(new DummyUploadable());
        $firstPath = self::storedPathOf($first);
        $secondPath = self::storedPathOf($second);
        self::assertMatchesRegularExpression('/^image-[^\/]+\.png$/', $firstPath);
        self::assertMatchesRegularExpression('/^image-[^\/]+\.png$/', $secondPath);
        self::assertNotSame($firstPath, $secondPath);
        self::assertStoredFilesOfResourceExist($first);
        self::assertStoredFilesOfResourceExist($second);
    }

    public function test_a_missing_source_image_skips_its_imagine_filters_instead_of_failing_the_resource(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters());
        self::requestJsonLd($client, 'GET', self::iriOf($resource));
        self::removeStoredFilesOf($resource);

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].contentUrl', self::downloadLinkOf($resource));
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[0].imagineFilter');
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[1]');
        self::assertMissingSourceImageLogged('thumbnail');
        self::assertMissingSourceImageLogged('square_thumbnail');
    }

    public function test_serialising_uploadables_whose_file_info_is_not_cached_yet_keeps_the_filenames_of_the_uploadables_serialised_before_them(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $first = self::createUploadable(new DummyUploadable());
        $second = self::createUploadable(new DummyUploadable());

        self::requestJsonLd($client, 'GET', '/dummy_uploadables');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('member[0]._metadata.mediaObjects.file[0].mimeType', 'image/png');
        self::assertJsonNodeSame('member[1]._metadata.mediaObjects.file[0].mimeType', 'image/png');
        self::assertJsonNodeNotExists('member[0].filename');
        self::assertJsonNodeNotExists('member[1].filename');
        self::assertHasStoredFile($first);
        self::assertHasStoredFile($second);
    }

    public function test_generating_imagine_filters_while_serialising_keeps_the_filenames_of_the_uploadables_serialised_before_them(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $first = self::createUploadable(new DummyUploadableWithImagineFilters());
        $second = self::createUploadable(new DummyUploadableWithImagineFilters());
        self::addFileInfo(self::storedPathOf($first));
        self::addFileInfo(self::storedPathOf($second));

        self::requestJsonLd($client, 'GET', '/dummy_uploadable_with_imagine_filters');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('member[1]._metadata.mediaObjects.file[1].imagineFilter', 'thumbnail');
        self::assertHasStoredFile($first);
        self::assertHasStoredFile($second);
    }

    public function test_an_imagine_filter_too_large_to_generate_under_the_memory_ceiling_is_skipped_on_read_and_the_original_is_still_returned(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resource = self::createUploadable(new DummyUploadableWithImagineFilters(), ['file' => 'oversized.png']);
        $this->setMemoryLimitJustAboveCurrentUsage();

        self::requestJsonLd($client, 'GET', self::iriOf($resource));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[0].imagineFilter');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].width', 7000);
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[1]');
        self::assertOversizedSourceImageLogged('thumbnail');
        self::assertOversizedSourceImageLogged('square_thumbnail');
    }

    public function test_uploading_an_image_too_large_to_filter_under_the_memory_ceiling_stores_it_and_skips_its_imagine_filters(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $this->setMemoryLimitJustAboveCurrentUsage();

        $this->uploadMultipart($client, '/dummy_uploadable_with_imagine_filters/upload', ['file' => 'oversized.png']);

        self::assertResponseStatusCodeSame(201);
        $iri = self::jsonNode('@id');
        self::assertJsonNodeSame('_metadata.mediaObjects.file[0].width', 7000);
        self::assertJsonNodeNotExists('_metadata.mediaObjects.file[1]');
        $resource = self::findByIri($iri);
        self::assertInstanceOf(DummyUploadableWithImagineFilters::class, $resource);
        self::assertHasStoredFile($resource);
        self::assertOversizedSourceImageLogged('thumbnail');
    }
}
