<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentBundle\Exception\OutOfBoundsException;
use Silverback\ApiComponentBundle\Helper\FileHelper;
use Silverback\ApiComponentBundle\Helper\UploadsHelper;

/**
 * Configures mapping between Uploads and MediaObject resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileListener
{
    private FileHelper $fileHelper;
    private UploadsHelper $uploadsHelper;

    public function __construct(FileHelper $fileHelper, UploadsHelper $uploadsHelper)
    {
        $this->fileHelper = $fileHelper;
        $this->uploadsHelper = $uploadsHelper;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $fileClassMetadata */
        $fileClassMetadata = $eventArgs->getClassMetadata();
        if (!$this->fileHelper->isConfigured($fileClassMetadata->getName())) {
            return;
        }

        $fileConfiguration = $this->fileHelper->getConfiguration($fileClassMetadata->getName());
        if (!$this->uploadsHelper->isConfigured($fileConfiguration->uploadsEntityClass)) {
            throw new OutOfBoundsException('The value of uploadsEntityClass on your MediaObject is not configured as an Uploads resource');
        }

        $uploadsConfiguration = $this->uploadsHelper->getConfiguration($fileConfiguration->uploadsEntityClass);

        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        /** @var ClassMetadataInfo $uploadsClassMetadata */
        $uploadsClassMetadata = $em->getClassMetadata($fileConfiguration->uploadsEntityClass);
        $namingStrategy = $em->getConfiguration()->getNamingStrategy();

        if (!$fileClassMetadata->hasAssociation($fileConfiguration->uploadsEntityClass)) {
            $fileClassMetadata->mapField([
                'fieldName' => $fileConfiguration->filePathFieldName,
                'nullable' => false,
            ]);

            $fileClassMetadata->mapField([
                'fieldName' => $fileConfiguration->uploadedAtFieldName,
                'type' => 'datetime',
                'nullable' => false,
            ]);

            $fileClassMetadata->mapManyToOne([
                'fieldName' => $fileConfiguration->uploadsFieldName,
                'targetEntity' => $fileConfiguration->uploadsEntityClass,
                'joinColumns' => [
                    [
                        'name' => $namingStrategy->joinKeyColumnName($uploadsClassMetadata->getName()),
                        'referencedColumnName' => $namingStrategy->referenceColumnName(),
                        'onDelete' => 'SET NULL',
                        'nullable' => true,
                    ],
                ],
                'inversedBy' => $uploadsConfiguration->fieldName,
            ]);
        }

        if (!$uploadsClassMetadata->hasAssociation($uploadsConfiguration->fieldName)) {
            $uploadsClassMetadata->mapOneToMany([
                'fieldName' => $uploadsConfiguration->fieldName,
                'targetEntity' => $fileClassMetadata->getName(),
                'mappedBy' => $fileConfiguration->uploadsEntityClass,
            ]);
        }

        $fileClassMetadata->addEntityListener('prePersist', __CLASS__, 'prePersist');
        $fileClassMetadata->addEntityListener('preUpdate', __CLASS__, 'preUpdate');
        $fileClassMetadata->addEntityListener('preRemove', __CLASS__, 'preRemove');
    }

    public function prePersist(object $object): void
    {
        $this->fileHelper->persistUploadedFile($object);
    }

    public function preUpdate(object $object, LifecycleEventArgs $args): void
    {
        $manager = $args->getEntityManager();
        $uow = $manager->getUnitOfWork();
        $this->fileHelper->persistUploadedFile($object, $uow->getEntityChangeSet($object));
    }

    public function preRemove(object $object): void
    {
        $this->fileHelper->removeFile($object);
    }
}
