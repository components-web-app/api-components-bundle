<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Depends;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublish;

final class UploadsTraitTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use UploadsTrait;

    public function test_an_uploadable_resource_is_created_with_its_file_stored_in_a_directory_of_this_process(): void
    {
        static::createClient();

        $resource = self::createUploadable(new DummyUploadable());

        $path = self::storedPathOf($resource);
        self::assertMatchesRegularExpression('/^image-[0-9a-f]{8}\.png$/', $path);
        self::assertSame([['local', $path]], self::storedFilesOf($resource));
        self::assertSame([['local', $path]], self::assertStoredFilesOfResourceExist($resource));
        self::assertStringEndsWith('/public/uploads/phpunit-' . getmypid(), self::uploadsDirectory());
        self::assertFileExists(self::uploadsDirectory() . '/' . $path);
    }

    public function test_every_field_named_is_uploaded(): void
    {
        static::createClient();

        $resource = self::createUploadable(new DummyUploadableRequiredOnPublish(), ['file' => 'image.png', 'preview' => 'image.svg']);

        $files = self::storedFilesOf($resource);
        self::assertCount(2, $files);
        self::assertStringEndsWith('.svg', $files[1][1]);
    }

    public function test_a_stored_file_removed_from_its_filestore_no_longer_exists(): void
    {
        static::createClient();
        $resource = self::createUploadable(new DummyUploadable());
        $files = self::storedFilesOf($resource);

        self::removeStoredFilesOf($resource);

        self::assertStoredFilesRemoved($files);
        self::assertStoredFileNotExists($files[0][1]);
    }

    public function test_asserting_a_missing_file_is_stored_fails(): void
    {
        static::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The file "missing.png" is not stored in the "local" filesystem.');

        self::assertStoredFileExists('missing.png');
    }

    public function test_a_data_uri_upload_is_stored(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::assertStringStartsWith('data:image/png;base64,', self::dataUriOf('image.png'));
        self::assertSame(self::base64Of('image.png'), substr(self::dataUriOf('image.png'), \strlen('data:image/png;base64,')));
        $response = self::requestJsonLd($client, 'POST', '/dummy_uploadables', ['file' => self::dataUriOf('image.png')]);

        self::assertResponseStatusCodeSame(201);
        $resource = self::findByIri($response->toArray()['@id']);
        self::assertInstanceOf(DummyUploadable::class, $resource);
        self::assertStoredFilesOfResourceExist($resource);
        self::assertFileEquals(self::assetPath('image.png'), self::uploadsDirectory() . '/' . self::storedPathOf($resource));
        self::assertSame(self::assetPath(), self::assetFile()->getPathname());
    }

    public function test_a_stored_file_is_written_and_can_be_aged(): void
    {
        static::createClient();

        self::writeStoredFile('young.txt');
        self::writeStoredFile('old.txt', true);

        self::assertStoredFileExists('young.txt');
        self::assertGreaterThan(time() - 60, filemtime(self::uploadsDirectory() . '/young.txt'));
        self::assertLessThanOrEqual(time() - 7200, filemtime(self::uploadsDirectory() . '/old.txt'));
        self::assertStoredFileExists('old.txt', 'public_url_local');

        self::emptyUploadFilestores();

        self::assertStoredFileNotExists('young.txt');
        self::assertStoredFileNotExists('old.txt', 'public_url_local');
    }

    public function test_file_info_rows_are_counted_by_filter(): void
    {
        static::createClient();

        self::addFileInfo('original.png');
        self::addFileInfo('original.png', 'image/webp', 10, 2, 2, 'thumbnail');
        self::addFileInfo('other.png', filter: 'thumbnail');

        self::assertSame(1, self::countFileInfoRows());
        self::assertSame(2, self::countFileInfoRows('thumbnail'));
        self::assertSame(0, self::countFileInfoRows('square'));
    }

    public function test_files_written_by_a_test_are_removed_after_it(): void
    {
        static::createClient();
        self::writeStoredFile('left-behind.txt');

        self::assertFileExists(self::uploadsDirectory() . '/left-behind.txt');
    }

    #[Depends('test_files_written_by_a_test_are_removed_after_it')]
    public function test_the_next_test_starts_with_no_stored_files(): void
    {
        self::assertDirectoryDoesNotExist(self::uploadsDirectory());
    }
}
