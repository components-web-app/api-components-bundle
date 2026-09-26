<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Ramsey\Uuid\Doctrine\UuidType;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\MappedSuperclassDiscriminatorMapListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableFileDeletionListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\RejectedDeleteException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\RejectedDeleteMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;

class UploadableFileDeletionListenerTest extends TestCase
{
    private EntityManager $entityManager;
    private Filesystem $filesystem;
    private UploadableFileDeletionListener $listener;
    private UploadableAttributeReader $reader;
    private UploadableFileManager $fileManager;
    private AbstractLogger $logger;
    private bool $failDeletes = false;

    protected function setUp(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([
            __DIR__ . '/../../../src/Entity',
            __DIR__ . '/../../Functional/TestBundle/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $configuration->setMiddlewares([new RejectedDeleteMiddleware()]);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);

        $events = $this->entityManager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new TablePrefixExtension('_acb_'));
        $events->addEventListener(Events::loadClassMetadata, new MappedSuperclassDiscriminatorMapListener());
        $events->addEventListener(Events::loadClassMetadata, new PublishableListener(new PublishableAttributeReader($registry)));
        $events->addEventListener(Events::loadClassMetadata, new UploadableListener(new UploadableAttributeReader($registry, true)));
        $events->addEventListener(Events::loadClassMetadata, new TimestampedListener(new TimestampedAttributeReader($registry)));
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $test = $this;
        $this->filesystem = new Filesystem(new class($test) extends InMemoryFilesystemAdapter {
            public function __construct(private readonly UploadableFileDeletionListenerTest $test)
            {
                parent::__construct();
            }

            public function delete(string $path): void
            {
                if ($this->test->deletesFail()) {
                    throw UnableToDeleteFile::atLocation($path, 'the filestore refused');
                }
                parent::delete($path);
            }
        });
        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($this->filesystem);

        $this->reader = new UploadableAttributeReader($registry, true);
        $this->fileManager = new UploadableFileManager(
            $registry,
            $this->reader,
            $filesystemProvider,
            $this->createStub(FlysystemDataLoader::class),
            new FileInfoCacheManager($this->entityManager, new FileInfoRepository($registry)),
            null,
            null
        );

        $this->logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };

        $this->listener = new UploadableFileDeletionListener($this->reader, $this->fileManager, $this->logger);
        $events->addEventListener([Events::onFlush, Events::postFlush], $this->listener);
    }

    protected function tearDown(): void
    {
        RejectedDeleteMiddleware::setRejectDeletes(false);
    }

    public function deletesFail(): bool
    {
        return $this->failDeletes;
    }

    public function test_removing_an_uploadable_deletes_its_stored_file_after_the_flush(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        $this->entityManager->remove($resource);
        $this->entityManager->flush();

        self::assertFalse($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertTrue($this->filesystem->fileExists('image-bbbbbbbb.png'));
    }

    public function test_files_collected_by_every_on_flush_are_deleted_when_the_flush_completes(): void
    {
        $first = $this->uploadable('image-aaaaaaaa.png');
        $second = $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        $this->entityManager->remove($first);
        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));
        $this->entityManager->getConnection()->executeStatement('DELETE FROM ' . $this->table(DummyUploadable::class) . ' WHERE filename = ?', ['image-aaaaaaaa.png']);
        $secondId = $second->getId();
        $this->entityManager->clear();
        $this->entityManager->remove($this->entityManager->find(DummyUploadable::class, $secondId));
        $this->entityManager->flush();

        self::assertFalse($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertFalse($this->filesystem->fileExists('image-bbbbbbbb.png'));
    }

    public function test_a_failed_file_delete_is_not_rethrown_when_no_logger_is_wired(): void
    {
        $this->entityManager->getEventManager()->removeEventListener([Events::onFlush, Events::postFlush], $this->listener);
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush, Events::postFlush], new UploadableFileDeletionListener($this->reader, $this->fileManager));
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();

        $this->failDeletes = true;
        $this->entityManager->remove($resource);
        $this->entityManager->flush();

        self::assertTrue($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $this->table(DummyUploadable::class)));
    }

    public function test_removing_an_uninitialised_reference_deletes_its_stored_file(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();
        $id = $resource->getId();
        $this->entityManager->clear();

        $this->entityManager->remove($this->entityManager->getReference(DummyUploadable::class, $id));
        $this->entityManager->flush();

        self::assertFalse($this->filesystem->fileExists('image-aaaaaaaa.png'));
    }

    public function test_the_file_info_rows_of_a_deleted_file_are_removed(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->persist(new FileInfo('image-aaaaaaaa.png', 'image/png', 10, 1, 1));
        $this->entityManager->persist(new FileInfo('image-bbbbbbbb.png', 'image/png', 10, 1, 1));
        $this->entityManager->flush();

        $this->entityManager->remove($resource);
        $this->entityManager->flush();

        self::assertSame(
            ['image-bbbbbbbb.png'],
            $this->entityManager->getConnection()->fetchFirstColumn('SELECT path FROM ' . $this->table(FileInfo::class))
        );
    }

    public function test_a_flush_that_fails_leaves_the_stored_file_in_place(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();

        RejectedDeleteMiddleware::setRejectDeletes(true);
        $this->entityManager->remove($resource);
        try {
            $this->entityManager->flush();
            self::fail('The flush was expected to fail.');
        } catch (DriverException $exception) {
            self::assertInstanceOf(RejectedDeleteException::class, $exception->getPrevious());
        }
        RejectedDeleteMiddleware::setRejectDeletes(false);

        self::assertTrue($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $this->table(DummyUploadable::class)));
    }

    public function test_a_path_another_row_still_references_is_kept(): void
    {
        $removed = $this->uploadable('image-aaaaaaaa.png');
        $this->uploadable('image-aaaaaaaa.png', false);
        $alsoRemoved = $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        $this->entityManager->remove($removed);
        $this->entityManager->remove($alsoRemoved);
        $this->entityManager->flush();

        self::assertTrue($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertFalse($this->filesystem->fileExists('image-bbbbbbbb.png'));
    }

    public function test_a_path_shared_by_two_rows_removed_in_one_flush_is_deleted(): void
    {
        $first = $this->uploadable('image-aaaaaaaa.png');
        $second = $this->uploadable('image-aaaaaaaa.png', false);
        $this->entityManager->flush();

        $this->entityManager->remove($first);
        $this->entityManager->remove($second);
        $this->entityManager->flush();

        self::assertFalse($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertSame([], $this->logger->records);
    }

    public function test_a_removed_entity_that_is_not_uploadable_or_has_no_file_deletes_nothing(): void
    {
        $this->filesystem->write('unrelated.png', 'not referenced by anything removed');
        $withoutFile = new DummyUploadable();
        $this->entityManager->persist($withoutFile);
        $group = new ComponentGroup();
        $group->reference = 'group';
        $group->location = 'group';
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);
        $this->entityManager->flush();

        $withFile = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();

        $this->entityManager->remove($group);
        $this->entityManager->remove($withoutFile);
        $this->entityManager->remove($withFile);
        $this->entityManager->flush();

        self::assertTrue($this->filesystem->fileExists('unrelated.png'));
        self::assertFalse($this->filesystem->fileExists('image-aaaaaaaa.png'));
        self::assertSame([], $this->logger->records);
    }

    public function test_a_file_that_cannot_be_deleted_is_logged_and_the_flush_still_succeeds(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $other = $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        $this->failDeletes = true;
        $this->entityManager->remove($resource);
        $this->entityManager->remove($other);
        $this->entityManager->flush();

        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $this->table(DummyUploadable::class)));
        self::assertCount(2, $this->logger->records);
        [$level, , $context] = $this->logger->records[0];
        self::assertSame('error', $level);
        self::assertSame('image-aaaaaaaa.png', $context['path']);
        self::assertSame('local', $context['adapter']);
        self::assertInstanceOf(UnableToDeleteFile::class, $context['exception']);
        self::assertSame('image-bbbbbbbb.png', $this->logger->records[1][2]['path']);
    }

    public function test_files_are_deleted_once_and_not_again_by_a_later_flush(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();
        $this->entityManager->remove($resource);
        $this->entityManager->flush();

        $this->filesystem->write('image-aaaaaaaa.png', 'a new file at the same path');
        $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        self::assertTrue($this->filesystem->fileExists('image-aaaaaaaa.png'));
    }

    public function test_reset_forgets_files_collected_by_a_flush_that_never_completed(): void
    {
        $resource = $this->uploadable('image-aaaaaaaa.png');
        $this->entityManager->flush();

        $this->entityManager->remove($resource);
        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));
        $this->listener->reset();
        $this->entityManager->getConnection()->executeStatement('DELETE FROM ' . $this->table(DummyUploadable::class));
        $this->entityManager->clear();

        $this->uploadable('image-bbbbbbbb.png');
        $this->entityManager->flush();

        self::assertTrue($this->filesystem->fileExists('image-aaaaaaaa.png'));
    }

    private function table(string $class): string
    {
        return $this->entityManager->getClassMetadata($class)->getTableName();
    }

    private function uploadable(string $path, bool $write = true): DummyUploadable
    {
        if ($write) {
            $this->filesystem->write($path, 'stored file');
        }
        $resource = new DummyUploadable();
        $resource->setFilename($path);
        $this->entityManager->persist($resource);

        return $resource;
    }
}
