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

namespace Silverback\ApiComponentBundle\Serializer\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Silverback\ApiComponentBundle\Annotation\Publishable;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Adds {CLASS}:publishable serialization group on {CLASS}.publishedAt for Publishable entities.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableLoader implements LoaderInterface
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadClassMetadata(ClassMetadataInterface $classMetadata)
    {
        /** @var Publishable $annotation */
        if (!$annotation = $this->reader->getClassAnnotation($classMetadata->getReflectionClass(), Publishable::class)) {
            return true;
        }

        if (!$attributeMetadata = ($classMetadata->getAttributesMetadata()[$annotation->fieldName] ?? null)) {
            throw new MappingException(sprintf('Groups on "%s::%s" cannot be added because the field does not exist.', $classMetadata->getName(), $annotation->fieldName));
        }

        $attributeMetadata->addGroup(sprintf('%s:publishable', $classMetadata->getName()));

        return true;
    }
}
