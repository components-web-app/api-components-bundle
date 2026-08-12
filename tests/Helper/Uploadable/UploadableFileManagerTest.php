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
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Named stub so the attribute reader has a real class to read UploadableField configuration from.
 * Both fields use the default storage property name, `filename`, as every UploadableField does
 * unless `property:` is set — which is what makes a marker keyed on the name alone ambiguous.
 */
#[Silverback\Uploadable]
class _LeakTestUploadable
{
    #[Silverback\UploadableField(adapter: 'local')]
    public ?File $file = null;

    public ?string $filename = null;
}

/**
 * Named stub for the clone path: a field with a `prefix:` so the copy has a directory to preserve.
 */
#[Silverback\Uploadable]
class _PrefixedTestUploadable
{
    #[Silverback\UploadableField(adapter: 'local', prefix: 'documents/')]
    public ?File $file = null;

    public ?string $filename = null;
}

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableFileManagerTest extends TestCase
{
    /**
     * Creating a draft of a publishable uploadable clones the stored file so the two resources own
     * their files independently. The copy belongs beside the original — the field's prefix is part of
     * the stored path — and carries a unique token like every other stored name.
     */
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

    /**
     * A stored file missing from the filestore is a recoverable storage problem. Nulling the clone's
     * path would discard the only record of what the file was, and publishing that draft would then
     * copy the null onto the published resource — turning a missing object into permanent loss.
     */
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

    /**
     * A "delete this file" marker belongs to the resource being written, not to the storage property
     * name. It used to be recorded on this shared service as the bare string 'filename' — the default
     * storage property of every UploadableField — and never cleared, so any later write carrying no
     * new file inherited it. Under a long-running runtime (FrankenPHP worker mode) that outlived the
     * request too: one admin file deletion poisoned every subsequent publish served by that worker,
     * silently deleting an unrelated resource's file and nulling its path.
     */
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

        // The admin clears a file: PATCH {"file": null}, which the denormalizer records as a deleted
        // field against the resource it is denormalizing before the file manager acts on it.
        $manager->addDeletedField($clearedResource, 'filename');
        $manager->persistFiles($clearedResource);

        self::assertFalse($filesystem->fileExists('image-aaa.png'));
        self::assertNull($clearedResource->filename);

        // A later write carrying no new file — a publish PATCH is exactly this — must not inherit
        // the marker, whether it happens in the same request or later in the same worker process.
        $manager->persistFiles($unrelatedResource);

        self::assertTrue(
            $filesystem->fileExists('image-bbb.png'),
            'An unrelated resource\'s file was deleted by a marker left over from an earlier write.'
        );
        self::assertSame('image-bbb.png', $unrelatedResource->filename);
    }

    /**
     * The counterpart: scoping the marker must not stop it working for the resource it was set on.
     */
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

    /**
     * Without any marker, a write that carries no new file leaves the stored file alone.
     */
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

    /**
     * Publishing a draft merges it into the published resource and the write continues against that
     * instance, so a request that clears a file and publishes in one go must still clear the file.
     */
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

    private function createFileManager(Filesystem $filesystem): UploadableFileManager
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
            null,
            null
        );
    }
}
