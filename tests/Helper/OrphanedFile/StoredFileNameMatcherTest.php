<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\OrphanedFile;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\StoredFileNameMatcher;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Model\Uploadable\DataUriFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StoredFileNameMatcherTest extends OrphanedResourceDatabaseTestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function originalNames(): iterable
    {
        yield 'a plain name' => ['image.png'];
        yield 'spaces, capitals and symbols' => ['My Holiday Photo (1)!.JPG'];
        yield 'no extension' => ['README'];
        yield 'nothing usable in the stem' => ['___.pdf'];
        yield 'a symbol in the extension' => ['archive.tar-gz'];
        yield 'a stem longer than 100 characters' => [str_repeat('a', 99) . '-' . str_repeat('b', 20) . '.png'];
        yield 'a stem cut at a hyphen' => [str_repeat('a', 99) . ' b.png'];
        yield 'digits only' => ['2024.csv'];
    }

    #[DataProvider('originalNames')]
    public function test_every_name_the_file_manager_tokenises_is_recognised(string $originalName): void
    {
        $tokenised = (new \ReflectionMethod(UploadableFileManager::class, 'tokeniseFilename'))->invoke($this->fileManager(), $originalName);

        self::assertTrue((new StoredFileNameMatcher())->matches($tokenised), $tokenised);
        self::assertTrue((new StoredFileNameMatcher())->matches('components/nested/' . $tokenised), $tokenised);
    }

    public function test_an_uploaded_file_stored_by_the_file_manager_is_recognised(): void
    {
        $uploadable = new DummyUploadable();
        $uploadable->file = new UploadedFile(__DIR__ . '/../../../features/assets/files/image.png', 'Team photo.PNG', null, null, true);
        $this->fileManager()->persistFiles($uploadable);

        self::assertNotNull($uploadable->getFilename());
        self::assertTrue((new StoredFileNameMatcher())->matches($uploadable->getFilename()), $uploadable->getFilename());
    }

    public function test_a_prefixed_copy_made_by_the_file_manager_is_recognised(): void
    {
        $original = new DummyUploadableAndPublishable();
        $original->file = new File(__DIR__ . '/../../../features/assets/files/image.png');
        $manager = $this->fileManager();
        $manager->persistFiles($original);
        $copy = $manager->processClonedUploadable($original, new DummyUploadableAndPublishable());

        self::assertStringStartsWith('components/', (string) $copy->getFilename());
        self::assertNotSame($original->getFilename(), $copy->getFilename());
        self::assertTrue((new StoredFileNameMatcher())->matches((string) $copy->getFilename()), (string) $copy->getFilename());
    }

    public function test_a_data_uri_upload_stored_by_the_file_manager_is_recognised(): void
    {
        $file = new DataUriFile('data:image/png;base64,' . base64_encode((string) file_get_contents(__DIR__ . '/../../../features/assets/files/image.png')));
        $uploadable = new DummyUploadable();
        $uploadable->file = new UploadedDataUriFile($file, Uuid::uuid4() . '.' . $file->getExtension());
        $this->fileManager()->persistFiles($uploadable);

        self::assertTrue((new StoredFileNameMatcher())->matches((string) $uploadable->getFilename()), (string) $uploadable->getFilename());
    }

    public function test_a_uuid_name_without_an_extension_is_recognised(): void
    {
        self::assertTrue((new StoredFileNameMatcher())->matches(Uuid::uuid4() . '.'));
        self::assertTrue((new StoredFileNameMatcher())->matches('uploads/' . Uuid::uuid4() . '.txt'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignNames(): iterable
    {
        yield 'a plain name' => ['logo.png'];
        yield 'a hyphenated name' => ['site-logo.png'];
        yield 'a token that is too short' => ['image-0a1b2c3.png'];
        yield 'a token that is too long' => ['image-0a1b2c3d4.png'];
        yield 'a token in capitals' => ['image-0A1B2C3D.png'];
        yield 'a token that is not hex' => ['image-0a1b2c3g.png'];
        yield 'no stem' => ['-0a1b2c3d.png'];
        yield 'a capital in the stem' => ['Image-0a1b2c3d.png'];
        yield 'a capital in the extension' => ['image-0a1b2c3d.PNG'];
        yield 'a stem longer than 100 characters' => [str_repeat('a', 101) . '-0a1b2c3d.png'];
        yield 'a token not at the end' => ['image-0a1b2c3d-copy.png'];
        yield 'a directory that looks tokenised' => ['image-0a1b2c3d/logo.png'];
        yield 'a uuid that is not version 4' => ['0f1b2c3d-4e5f-1a7b-8c9d-0e1f2a3b4c5d.png'];
        yield 'a uuid without its dot' => ['0f1b2c3d-4e5f-4a7b-8c9d-0e1f2a3b4c5d'];
        yield 'a uuid with a suffix' => ['0f1b2c3d-4e5f-4a7b-8c9d-0e1f2a3b4c5d-old.png'];
        yield 'a uuid with a prefix' => ['old-0f1b2c3d-4e5f-4a7b-8c9d-0e1f2a3b4c5d.png'];
    }

    #[DataProvider('foreignNames')]
    public function test_a_name_the_bundle_does_not_generate_is_not_recognised(string $path): void
    {
        self::assertFalse((new StoredFileNameMatcher())->matches($path));
    }

    private function fileManager(): UploadableFileManager
    {
        return new UploadableFileManager(
            $this->registry,
            new UploadableAttributeReader($this->registry, true),
            new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem])),
            $this->createStub(FlysystemDataLoader::class),
            $this->createStub(FileInfoCacheManager::class),
            null,
            null
        );
    }
}
