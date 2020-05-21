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

namespace Silverback\ApiComponentsBundle\Helper\Timestamped;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedDataPersister
{
    use ClassMetadataTrait;

    private TimestampedAnnotationReader $annotationReader;

    public function __construct(ManagerRegistry $registry, TimestampedAnnotationReader $annotationReader)
    {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
    }

    public function persistTimestampedFields(object $timestamped, bool $isNew): void
    {
        $configuration = $this->annotationReader->getConfiguration($timestamped);
        $classMetadata = $this->getClassMetadata($timestamped);
        $classMetadata->setFieldValue(
            $timestamped,
            $configuration->createdAtField,
            $isNew ?
            new \DateTimeImmutable() :
            $classMetadata->getFieldValue($timestamped, $configuration->createdAtField)
        );
        $classMetadata->setFieldValue($timestamped, $configuration->modifiedAtField, new \DateTime());
    }
}
