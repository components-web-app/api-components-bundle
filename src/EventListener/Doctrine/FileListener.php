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
    private FileHelper $mediaObjectHelper;
    private UploadsHelper $uploadsHelper;

    public function __construct(FileHelper $mediaObjectHelper, UploadsHelper $uploadsHelper)
    {
        $this->mediaObjectHelper = $mediaObjectHelper;
        $this->uploadsHelper = $uploadsHelper;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $mediaObjectClassMetadata */
        $mediaObjectClassMetadata = $eventArgs->getClassMetadata();
        if (!$this->mediaObjectHelper->isConfigured($mediaObjectClassMetadata->getName())) {
            return;
        }

        $mediaObjectConfiguration = $this->mediaObjectHelper->getConfiguration($mediaObjectClassMetadata->getName());
        if (!$this->uploadsHelper->isConfigured($mediaObjectConfiguration->uploadsEntityClass)) {
            throw new OutOfBoundsException('The value of uploadsEntityClass on your MediaObject is not configured as an Uploads resource');
        }

        $uploadsConfiguration = $this->uploadsHelper->getConfiguration($mediaObjectConfiguration->uploadsEntityClass);

        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        /** @var ClassMetadataInfo $mediaObjectClassMetadata */
        $uploadsClassMetadata = $em->getClassMetadata($mediaObjectConfiguration->uploadsEntityClass);
        $namingStrategy = $em->getConfiguration()->getNamingStrategy();

        if (!$mediaObjectClassMetadata->hasAssociation($mediaObjectConfiguration->uploadsEntityClass)) {
            $mediaObjectClassMetadata->mapField([
                'fieldName' => $mediaObjectConfiguration->filePathFieldName,
                'nullable' => false,
            ]);

            $mediaObjectClassMetadata->mapManyToOne([
                'fieldName' => $mediaObjectConfiguration->uploadsFieldName,
                'targetEntity' => $mediaObjectConfiguration->uploadsEntityClass,
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
                'targetEntity' => $mediaObjectClassMetadata->getName(),
                'mappedBy' => $mediaObjectConfiguration->uploadsEntityClass,
            ]);
        }
    }
}
