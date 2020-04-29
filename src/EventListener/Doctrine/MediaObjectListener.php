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
use Silverback\ApiComponentBundle\Helper\MediaObjectHelper;
use Silverback\ApiComponentBundle\Helper\UploadableHelper;

/**
 * Configures mapping between Uploadable and MediaObject resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class MediaObjectListener
{
    private MediaObjectHelper $mediaObjectHelper;
    private UploadableHelper $uploadableHelper;

    public function __construct(MediaObjectHelper $mediaObjectHelper, UploadableHelper $uploadableHelper)
    {
        $this->mediaObjectHelper = $mediaObjectHelper;
        $this->uploadableHelper = $uploadableHelper;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $mediaObjectClassMetadata */
        $mediaObjectClassMetadata = $eventArgs->getClassMetadata();
        if (!$this->mediaObjectHelper->isConfigured($mediaObjectClassMetadata->getName())) {
            return;
        }

        $mediaObjectConfiguration = $this->mediaObjectHelper->getConfiguration($mediaObjectClassMetadata->getName());
        if (!$this->uploadableHelper->isConfigured($mediaObjectConfiguration->uploadableEntityClass)) {
            throw new OutOfBoundsException('The value of uploadableEntityClass on your MediaObject is not configured as an Uploadable resource');
        }

        $uploadableConfiguration = $this->uploadableHelper->getConfiguration($mediaObjectConfiguration->uploadableEntityClass);

        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        /** @var ClassMetadataInfo $mediaObjectClassMetadata */
        $uploadableClassMetadata = $em->getClassMetadata($mediaObjectConfiguration->uploadableEntityClass);
        $namingStrategy = $em->getConfiguration()->getNamingStrategy();

        if (!$mediaObjectClassMetadata->hasAssociation($mediaObjectConfiguration->uploadableEntityClass)) {
            $mediaObjectClassMetadata->mapManyToOne([
                'fieldName' => $mediaObjectConfiguration->uploadableFieldName,
                'targetEntity' => $mediaObjectConfiguration->uploadableEntityClass,
                'joinColumns' => [
                    [
                        'name' => $namingStrategy->joinKeyColumnName($uploadableClassMetadata->getName()),
                        'referencedColumnName' => $namingStrategy->referenceColumnName(),
                        'onDelete' => 'SET NULL',
                    ],
                ],
                'inversedBy' => $uploadableConfiguration->fieldName,
            ]);
        }

        if (!$mediaObjectClassMetadata->hasAssociation($uploadableConfiguration->fieldName)) {
            $mediaObjectClassMetadata->mapOneToMany([
                'fieldName' => $uploadableConfiguration->fieldName,
                'targetEntity' => $mediaObjectClassMetadata->getName(),
                'mappedBy' => $mediaObjectConfiguration->uploadableEntityClass,
            ]);
        }
    }
}
