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
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedHelper;
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
    private TimestampedHelper $timestampedHelper;

    public function __construct(ManagerRegistry $registry, TimestampedAnnotationReader $annotationReader, TimestampedHelper $timestampedHelper)
    {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->timestampedHelper = $timestampedHelper;
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
        $this->timestampedHelper->persistTimestampedFields($object, $isNew);

        return $object;
    }
}
