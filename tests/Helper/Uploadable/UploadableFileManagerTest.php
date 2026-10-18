<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Uploadable;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Silverback\ApiComponentsBundle\Model\Uploadable\DataUriFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Tests\Flysystem\RecordingFilesystemAdapter;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/../../Flysystem/cloud_adapter_stubs.php';

#[Silverback\Uploadable]
class _LeakTestUploadable
{
    #[Silverback\UploadableField(adapter: 'local')]
    public ?File $file = null;

    public ?string $filename = null;
}

#[Silverback\Uploadable]
class _PrefixedTestUploadable
{
    #[Silverback\UploadableField(adapter: 'local', prefix: 'documents/')]
    public ?File $file = null;

    public ?string $filename = null;
}

class _NotUploadableStub
{
    public ?string $filename = null;
}

#[Silverback\Uploadable]
class _CacheControlOverrideUploadable
{
    #[Silverback\UploadableField(adapter: 'local', cacheControl: 'private, max-age=60')]
    public ?File $file = null;

    public ?string $filename = null;
}

#[Silverback\Uploadable]
class _NoCacheControlUploadable
{
    #[Silverback\UploadableField(adapter: 'local', cacheControl: false)]
    public ?File $file = null;

    public ?string $filename = null;
}

#[Silverback\Uploadable]
class _ImagineCacheControlUploadable implements ImagineFiltersInterface
{
    #[Silverback\UploadableField(adapter: 'local', imagineCacheControl: 'public, max-age=600')]
    public ?File $file = null;

    public ?string $filename = null;

    public function getImagineFilters(string $property, ?Request $request): array
    {
        return ['thumbnail', 'square'];
    }
}

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableFileManagerTest extends TestCase
{
    private const string ORIGINAL = 'public, max-age=31536000, immutable';
    private const string IMAGINE = 'public, max-age=2592000';

    public function test_orphaned_file_deletion_removes_a_path_the_resource_no_longer_references(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('components/old-aaaaaaaa.png', 'the replaced file');
        $filesystem->write('components/new-bbbbbbbb.png', 'the replacement');

        $manager = $this->createFileManager($filesystem);

        $resource = new _LeakTestUploadable();
        $resource->filename = 'components/old-aaaaaaaa.png';

        $previousPaths = $manager->getStoredFilePaths($resource);
        self::assertSame(['filename' => 'components/old-aaaaaaaa.png'], $previousPaths);

        $resource->filename = 'components/new-bbbbbbbb.png';
        $manager->deleteOrphanedFiles($resource, $previousPaths);

        self::assertFalse($filesystem->fileExists('components/old-aaaaaaaa.png'));
        self::assertTrue($filesystem->fileExists('components/new-bbbbbbbb.png'));
    }

    public function test_orphaned_file_deletion_keeps_a_path_the_resource_still_references(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('components/kept-aaaaaaaa.png', 'the file both resources point at');

        $manager = $this->createFileManager($filesystem);

        $resource = new _LeakTestUploadable();
        $resource->filename = 'components/kept-aaaaaaaa.png';

        $previousPaths = $manager->getStoredFilePaths($resource);
        $manager->deleteOrphanedFiles($resource, $previousPaths);

        self::assertTrue($filesystem->fileExists('components/kept-aaaaaaaa.png'));
        self::assertSame('components/kept-aaaaaaaa.png', $resource->filename);
    }

    public function test_orphaned_file_deletion_removes_the_previous_file_when_the_field_is_now_empty(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('components/old-aaaaaaaa.png', 'the file being dropped');

        $manager = $this->createFileManager($filesystem);

        $resource = new _LeakTestUploadable();
        $resource->filename = 'components/old-aaaaaaaa.png';

        $previousPaths = $manager->getStoredFilePaths($resource);

        $resource->filename = null;
        $manager->deleteOrphanedFiles($resource, $previousPaths);

        self::assertFalse($filesystem->fileExists('components/old-aaaaaaaa.png'));
    }

    public function test_orphaned_file_deletion_is_a_no_op_for_a_resource_that_is_not_uploadable(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('components/old-aaaaaaaa.png', 'nothing to do with this resource');

        $manager = $this->createFileManager($filesystem);

        self::assertSame([], $manager->getStoredFilePaths(new _NotUploadableStub()));

        $manager->deleteOrphanedFiles(new _NotUploadableStub(), ['filename' => 'components/old-aaaaaaaa.png']);

        self::assertTrue($filesystem->fileExists('components/old-aaaaaaaa.png'));
    }

    public function test_cloning_an_uploadable_copies_the_file_alongside_the_original_with_a_tokenised_name(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('documents/report-aaaaaaaa.pdf', 'the original');

        $manager = $this->createFileManager($filesystem);

        $published = new _PrefixedTestUploadable();
        $published->filename = 'documents/report-aaaaaaaa.pdf';
        $draft = new _PrefixedTestUploadable();

        $manager->processClonedUploadable($published, $draft);

        self::assertMatchesRegularExpression('#^documents/report-[0-9a-f]{8}\.pdf$#', (string) $draft->filename);
        self::assertNotSame($published->filename, $draft->filename);
        self::assertTrue($filesystem->fileExists((string) $draft->filename));
        self::assertTrue($filesystem->fileExists($published->filename), 'The clone must not disturb the original.');
    }

    public function test_cloning_an_uploadable_whose_stored_file_is_missing_keeps_the_original_path(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $manager = $this->createFileManager($filesystem);

        $published = new _PrefixedTestUploadable();
        $published->filename = 'documents/gone-aaaaaaaa.pdf';
        $draft = new _PrefixedTestUploadable();

        $manager->processClonedUploadable($published, $draft);

        self::assertSame('documents/gone-aaaaaaaa.pdf', $draft->filename);
    }

    public function test_a_deleted_field_marked_on_one_object_does_not_delete_another_objects_file(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image-aaa.png', 'file of the resource whose file is being cleared');
        $filesystem->write('image-bbb.png', 'file of an unrelated resource');

        $manager = $this->createFileManager($filesystem);

        $clearedResource = new _LeakTestUploadable();
        $clearedResource->filename = 'image-aaa.png';

        $unrelatedResource = new _LeakTestUploadable();
        $unrelatedResource->filename = 'image-bbb.png';

        $manager->addDeletedField($clearedResource, 'filename');
        $manager->persistFiles($clearedResource);

        self::assertFalse($filesystem->fileExists('image-aaa.png'));
        self::assertNull($clearedResource->filename);

        $manager->persistFiles($unrelatedResource);

        self::assertTrue(
            $filesystem->fileExists('image-bbb.png'),
            'An unrelated resource\'s file was deleted by a marker left over from an earlier write.'
        );
        self::assertSame('image-bbb.png', $unrelatedResource->filename);
    }

    public function test_a_deleted_field_still_removes_the_file_for_the_object_it_was_marked_on(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image-aaa.png', 'file to be cleared');

        $manager = $this->createFileManager($filesystem);

        $resource = new _LeakTestUploadable();
        $resource->filename = 'image-aaa.png';

        $manager->addDeletedField($resource, 'filename');
        $manager->persistFiles($resource);

        self::assertFalse($filesystem->fileExists('image-aaa.png'));
        self::assertNull($resource->filename);
    }

    public function test_a_write_with_no_new_file_and_no_marker_leaves_the_stored_file_alone(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image-aaa.png', 'file that must survive');

        $manager = $this->createFileManager($filesystem);

        $resource = new _LeakTestUploadable();
        $resource->filename = 'image-aaa.png';

        $manager->persistFiles($resource);

        self::assertTrue($filesystem->fileExists('image-aaa.png'));
        self::assertSame('image-aaa.png', $resource->filename);
    }

    public function test_a_deleted_field_marker_follows_a_draft_when_it_is_merged_into_its_published_resource(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('image-aaa.png', 'file the merged resource carries');

        $manager = $this->createFileManager($filesystem);

        $draft = new _LeakTestUploadable();
        $published = new _LeakTestUploadable();
        $published->filename = 'image-aaa.png';

        $manager->addDeletedField($draft, 'filename');
        $manager->transferDeletedFields($draft, $published);
        $manager->persistFiles($published);

        self::assertFalse($filesystem->fileExists('image-aaa.png'));
        self::assertNull($published->filename);
    }

    public function test_an_upload_is_stored_with_the_extension_of_its_content_not_its_client_name(): void
    {
        self::assertMatchesRegularExpression('#^holiday-[0-9a-f]{8}\.png$#', $this->storeUpload('image.png', 'holiday.jpg'));
    }

    public function test_an_upload_with_no_client_extension_is_stored_with_the_extension_of_its_content(): void
    {
        self::assertMatchesRegularExpression('#^scan-[0-9a-f]{8}\.png$#', $this->storeUpload('image.png', 'scan'));
    }

    public function test_an_upload_whose_client_name_has_no_extension_is_named_after_a_file_that_has_one(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $object = new _LeakTestUploadable();
        $object->file = new UploadedFile(__DIR__ . '/../../Functional/assets/files/image.png', 'file', null, null, true);

        $this->createFileManager($filesystem)->persistFiles($object);

        self::assertMatchesRegularExpression('#^image-[0-9a-f]{8}\.png$#', (string) $object->filename);
    }

    public function test_an_upload_keeps_a_client_extension_that_is_valid_for_its_content(): void
    {
        self::assertMatchesRegularExpression('#^report-[0-9a-f]{8}\.docx$#', $this->storeUpload('test_file.docx', 'report.docx'));
        self::assertMatchesRegularExpression('#^vector-[0-9a-f]{8}\.svgz$#', $this->storeUpload('image.svg', 'vector.svgz'));
    }

    public function test_an_upload_whose_content_is_only_known_to_be_plain_text_keeps_its_client_extension(): void
    {
        self::assertMatchesRegularExpression('#^notes-[0-9a-f]{8}\.md$#', $this->storeUpload('test_file.txt', 'notes.md'));
        self::assertMatchesRegularExpression('#^notes-[0-9a-f]{8}\.txt$#', $this->storeUpload('test_file.txt', 'notes'));
    }

    public function test_a_file_given_without_a_client_name_is_stored_with_the_extension_of_its_content(): void
    {
        $source = sys_get_temp_dir() . '/acb-' . bin2hex(random_bytes(4)) . '.jpg';
        copy(__DIR__ . '/../../Functional/assets/files/image.png', $source);
        try {
            $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
            $object = new _LeakTestUploadable();
            $object->file = new File($source);

            $this->createFileManager($filesystem)->persistFiles($object);

            self::assertMatchesRegularExpression('#^acb-[0-9a-f]{8}-[0-9a-f]{8}\.png$#', (string) $object->filename);
        } finally {
            unlink($source);
        }
    }

    public function test_an_upload_to_google_cloud_storage_carries_the_cache_control_beside_its_content_type(): void
    {
        [$filesystem, $adapter, $factory] = $this->googleCloudStorage();
        $object = new _LeakTestUploadable();
        $object->file = $this->upload('image.png', 'photo.png');

        $this->createFileManager($filesystem, $factory)->persistFiles($object);

        $config = $adapter->writes[$object->filename];
        self::assertSame('image/png', $config->get('mimetype'));
        self::assertSame(['contentType' => 'image/png', 'cacheControl' => self::ORIGINAL], $config->get('metadata'));
    }

    public function test_a_data_uri_upload_carries_the_cache_control(): void
    {
        [$filesystem, $adapter, $factory] = $this->googleCloudStorage();
        $object = new _LeakTestUploadable();
        $dataUri = new DataUriFile('data:image/png;base64,' . base64_encode((string) file_get_contents(__DIR__ . '/../../Functional/assets/files/image.png')));
        $object->file = new UploadedDataUriFile($dataUri, '6a3f5e2c-1b4d-4e8f-9a0b-1c2d3e4f5a6b.png');

        $this->createFileManager($filesystem, $factory)->persistFiles($object);

        self::assertSame('6a3f5e2c-1b4d-4e8f-9a0b-1c2d3e4f5a6b.png', $object->filename);
        self::assertSame(['contentType' => 'image/png', 'cacheControl' => self::ORIGINAL], $adapter->writes[$object->filename]->get('metadata'));
    }

    public function test_an_upload_carries_its_fields_cache_control_override(): void
    {
        [$filesystem, $adapter, $factory] = $this->googleCloudStorage();
        $object = new _CacheControlOverrideUploadable();
        $object->file = $this->upload('image.png', 'photo.png');

        $this->createFileManager($filesystem, $factory)->persistFiles($object);

        self::assertSame(['contentType' => 'image/png', 'cacheControl' => 'private, max-age=60'], $adapter->writes[$object->filename]->get('metadata'));
    }

    public function test_an_upload_for_a_field_that_sets_no_cache_control_carries_none(): void
    {
        [$filesystem, $adapter, $factory] = $this->googleCloudStorage();
        $object = new _NoCacheControlUploadable();
        $object->file = $this->upload('image.png', 'photo.png');

        $this->createFileManager($filesystem, $factory)->persistFiles($object);

        self::assertSame(['contentType' => 'image/png'], $adapter->writes[$object->filename]->get('metadata'));
    }

    public function test_variants_generated_after_an_upload_use_the_fields_imagine_cache_control(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write('photo-1a2b3c4d.png', (string) file_get_contents(__DIR__ . '/../../Functional/assets/files/image.png'));
        $object = new _ImagineCacheControlUploadable();
        $object->filename = 'photo-1a2b3c4d.png';

        $cacheControl = new UploadableCacheControl(new FilesystemFactory(new ServiceLocator([])), self::ORIGINAL, self::IMAGINE);
        $generated = [];
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static function (string $path, string $filter) use ($cacheControl, &$generated): string {
            $generated[$filter] = $cacheControl->forVariantOf($path);

            return '/media/cache/' . $filter . '/' . $path;
        });
        $liipCacheManager = $this->createStub(CacheManager::class);
        $liipCacheManager->method('isStored')->willReturn(true);

        $this->createFileManager($filesystem, null, $cacheControl, new ImagineFilterGenerator($filterService, $liipCacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', null))->storeFilesMetadata($object);

        self::assertSame(['thumbnail' => 'public, max-age=600', 'square' => 'public, max-age=600'], $generated);
    }

    /**
     * @return array{Filesystem, RecordingFilesystemAdapter, FilesystemFactory}
     */
    private function googleCloudStorage(): array
    {
        $adapter = new GoogleCloudStorageAdapter();
        $factory = new FilesystemFactory(new ServiceLocator(['local' => static fn () => $adapter]));

        return [$factory->create('local'), $adapter, $factory];
    }

    private function upload(string $asset, string $clientName): UploadedFile
    {
        $temporaryUpload = tempnam(sys_get_temp_dir(), 'php');
        copy(__DIR__ . '/../../Functional/assets/files/' . $asset, $temporaryUpload);

        return new UploadedFile($temporaryUpload, $clientName, null, null, true);
    }

    private function storeUpload(string $asset, string $clientName): string
    {
        $temporaryUpload = tempnam(sys_get_temp_dir(), 'php');
        copy(__DIR__ . '/../../Functional/assets/files/' . $asset, $temporaryUpload);
        try {
            $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
            $object = new _LeakTestUploadable();
            $object->file = new UploadedFile($temporaryUpload, $clientName, null, null, true);

            $this->createFileManager($filesystem)->persistFiles($object);

            self::assertTrue($filesystem->fileExists((string) $object->filename));

            return (string) $object->filename;
        } finally {
            unlink($temporaryUpload);
        }
    }

    private function createFileManager(Filesystem $filesystem, ?FilesystemFactory $factory = null, ?UploadableCacheControl $cacheControl = null, ?ImagineFilterGenerator $filterGenerator = null): UploadableFileManager
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata
            ->method('getFieldValue')
            ->willReturnCallback(static fn (object $entity, string $field): mixed => $entity->{$field});
        $classMetadata
            ->method('setFieldValue')
            ->willReturnCallback(static function (object $entity, string $field, mixed $value): void {
                $entity->{$field} = $value;
            });

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        return new UploadableFileManager(
            $registry,
            new UploadableAttributeReader($registry, false),
            $filesystemProvider,
            $this->createStub(FlysystemDataLoader::class),
            $this->createStub(FileInfoCacheManager::class),
            $cacheControl ?? new UploadableCacheControl($factory ?? new FilesystemFactory(new ServiceLocator([])), self::ORIGINAL, self::IMAGINE),
            null,
            $filterGenerator,
        );
    }
}
