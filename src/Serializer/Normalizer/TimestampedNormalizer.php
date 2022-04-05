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

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
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

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
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

        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);
        $this->timestampedDataPersister->persistTimestampedFields($object, $isNew);

        return $object;
    }
}
