<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedListener
{
    use ClassMetadataTrait;

    private TimestampedAnnotationReader $timestampedHelper;

    public function __construct(TimestampedAnnotationReader $timestampedHelper, ManagerRegistry $managerRegistry)
    {
        $this->timestampedHelper = $timestampedHelper;
        $this->initRegistry($managerRegistry);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if (!$this->timestampedHelper->isConfigured($metadata->getName())) {
            return;
        }

        $configuration = $this->timestampedHelper->getConfiguration($metadata->getName());

        if (!$metadata->hasField($configuration->createdAtField)) {
            $metadata->mapField([
                'fieldName' => $configuration->createdAtField,
                'type' => 'datetime_immutable',
                'nullable' => false,
            ]);
        }

        if (!$metadata->hasField($configuration->modifiedAtField)) {
            $metadata->mapField([
                'fieldName' => $configuration->modifiedAtField,
                'type' => 'datetime',
                'nullable' => false,
            ]);
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->setFields($args->getObject(), true);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->setFields($args->getObject(), false);
    }

    private function setFields(object $timestamped, bool $isNew): void
    {
        if (!$this->timestampedHelper->isConfigured($timestamped)) {
            return;
        }

        $config = $this->timestampedHelper->getConfiguration($timestamped);
        $classMetadata = $this->getClassMetadata($timestamped);
        $classMetadata->setFieldValue(
            $timestamped,
            $config->createdAtField,
            $isNew ?
                new \DateTimeImmutable() :
                $classMetadata->getFieldValue($timestamped, $config->createdAtField)
        );
        $classMetadata->setFieldValue($timestamped, $config->modifiedAtField, new \DateTime());
    }
}
