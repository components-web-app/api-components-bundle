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
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedListener
{
    private TimestampedAnnotationReader $annotationReader;

    public function __construct(TimestampedAnnotationReader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if (!$this->annotationReader->isConfigured($metadata->getName())) {
            return;
        }

        $configuration = $this->annotationReader->getConfiguration($metadata->getName());

        if (!$metadata->hasField($configuration->createdAtField)) {
            $metadata->mapField(
                [
                    'fieldName' => $configuration->createdAtField,
                    'type' => 'datetime_immutable',
                    'nullable' => false,
                ]
            );
        }

        if (!$metadata->hasField($configuration->modifiedAtField)) {
            $metadata->mapField(
                [
                    'fieldName' => $configuration->modifiedAtField,
                    'type' => 'datetime',
                    'nullable' => false,
                ]
            );
        }
    }
}
