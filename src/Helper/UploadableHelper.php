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

namespace Silverback\ApiComponentBundle\Helper;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Annotation\Uploadable;
use Silverback\ApiComponentBundle\Annotation\UploadableField;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableHelper extends AbstractHelper
{
    public function __construct(Reader $reader, ManagerRegistry $registry)
    {
        $this->initRegistry($registry);
        $this->initReader($reader);
    }

    public function isFieldConfigured(\ReflectionProperty $property): bool
    {
        try {
            $this->getFieldConfiguration($property);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    public function getFieldConfiguration(\ReflectionProperty $property): UploadableField
    {
        /** @var UploadableField|null $annotation */
        if (!$annotation = $this->reader->getPropertyAnnotation($property, UploadableField::class)) {
            throw new InvalidArgumentException(sprintf('%s::%s does not have %s annotation', $property->getDeclaringClass()->getName(), $property->getName(), UploadableField::class));
        }

        return $annotation;
    }

    public function getFields(object $data): iterable
    {
        foreach ($this->getClassMetadata($data)->getFieldNames() as $fieldName) {
            if ($this->isFieldConfigured(new \ReflectionProperty($data, $fieldName))) {
                yield $fieldName;
            }
        }
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): Uploadable
    {
        return $this->getClassAnnotationConfiguration($class, Uploadable::class);
    }
}
