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
use Silverback\ApiComponentsBundle\AnnotationReader\TimestampedAnnotationReader;
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

    private TimestampedAnnotationReader $annotationReader;
    private TimestampedDataPersister $timestampedDataPersister;

    public function __construct(ManagerRegistry $registry, TimestampedAnnotationReader $annotationReader, TimestampedDataPersister $timestampedDataPersister)
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
        return !isset($context[self::ALREADY_CALLED]) && $this->annotationReader->isConfigured($type);
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        $isNew = !isset($context[AbstractNormalizer::OBJECT_TO_POPULATE]);

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);
        $this->timestampedDataPersister->persistTimestampedFields($object, $isNew);

        return $object;
    }
}
