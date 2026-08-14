<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedNormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use ClassMetadataTrait;

    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'TIMESTAMPED_NORMALIZER_ALREADY_CALLED';

    private TimestampedAttributeReader $annotationReader;
    private TimestampedDataPersister $timestampedDataPersister;

    public function __construct(ManagerRegistry $registry, TimestampedAttributeReader $annotationReader, TimestampedDataPersister $timestampedDataPersister)
    {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->timestampedDataPersister = $timestampedDataPersister;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }
        $id = $type;

        return !\in_array($id, $context[self::ALREADY_CALLED], true) && $this->annotationReader->isConfigured($type);
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED][] = $type;

        $objectToPopulate = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $isNew = null === $objectToPopulate;

        // The creation date is not the client's to set. TimestampedDataPersister keeps whatever
        // value the object carries when it is not new, so a `createdAt` in the request body would
        // otherwise be written straight through. Capture the persisted value up front and put it
        // back afterwards: reading it after denormalization is too late, it has already been
        // replaced. TimestampedTrait's setCreatedAt() ignores a second value and so happens to
        // block this, but an application entity is under no obligation to use the trait or to
        // guard its own setter, so the protection has to live here.
        //
        // Only the resource being written has a creation date worth keeping. Denormalizing a
        // collection property puts the Doctrine PersistentCollection in OBJECT_TO_POPULATE while
        // $type is still the entity, so the instance check is what keeps this off anything that is
        // not the object under construction.
        $persistedCreatedAt = $objectToPopulate instanceof $type ? $this->getCreatedAt($objectToPopulate) : null;

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (null !== $persistedCreatedAt) {
            $configuration = $this->annotationReader->getConfiguration($object);
            $this->getClassMetadata($object)->setFieldValue($object, $configuration->createdAtField, $persistedCreatedAt);
        }

        $this->timestampedDataPersister->persistTimestampedFields($object, $isNew);

        return $object;
    }

    private function getCreatedAt(object $object): ?\DateTimeInterface
    {
        $configuration = $this->annotationReader->getConfiguration($object);

        return $this->getClassMetadata($object)->getFieldValue($object, $configuration->createdAtField);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
