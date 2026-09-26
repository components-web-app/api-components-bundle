<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Publishable;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidType;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\TablePrefixExtension;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\MappedSuperclassDiscriminatorMapListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableDraftMerger;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

trait PublishableDatabaseTrait
{
    private EntityManager $entityManager;
    private ManagerRegistry $registry;
    private PublishableStatusChecker $publishableStatusChecker;
    private PublishableDraftMerger $publishableDraftMerger;

    private function setUpPublishableDatabase(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $configuration = ORMSetup::createAttributeMetadataConfig([
            __DIR__ . '/../../../src/Entity',
            __DIR__ . '/../../Functional/TestBundle/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->registry = $registry;

        $events = $this->entityManager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new TablePrefixExtension('_acb_'));
        $events->addEventListener(Events::loadClassMetadata, new MappedSuperclassDiscriminatorMapListener());
        $events->addEventListener(Events::loadClassMetadata, new PublishableListener(new PublishableAttributeReader($registry)));
        $events->addEventListener(Events::loadClassMetadata, new UploadableListener(new UploadableAttributeReader($registry, true)));
        $events->addEventListener(Events::loadClassMetadata, new TimestampedListener(new TimestampedAttributeReader($registry)));
        (new SchemaTool($this->entityManager))->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->publishableStatusChecker = new PublishableStatusChecker($registry, new PublishableAttributeReader($registry), $this->createStub(AuthorizationCheckerInterface::class), 'is_granted("ROLE_ADMIN")');
        $fileManager = new UploadableFileManager(
            $registry,
            new UploadableAttributeReader($registry, false),
            $this->createStub(FilesystemProvider::class),
            $this->createStub(FlysystemDataLoader::class),
            $this->createStub(FileInfoCacheManager::class),
            null,
        );
        $this->publishableDraftMerger = new PublishableDraftMerger($this->publishableStatusChecker, $registry, $fileManager);
    }

    /**
     * @return array{DummyPublishableComponent, DummyPublishableComponent}
     */
    private function persistPublishedWithDraft(\DateTimeInterface $publishedAt, ?\DateTimeInterface $draftPublishedAt): array
    {
        $published = new DummyPublishableComponent();
        $published->reference = 'published';
        $published->setPublishedAt($publishedAt);
        $this->entityManager->persist($published);

        $draft = new DummyPublishableComponent();
        $draft->reference = 'draft';
        $draft->setPublishedAt($draftPublishedAt);
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);
        $this->entityManager->flush();
        $this->entityManager->refresh($published);

        return [$published, $draft];
    }
}
