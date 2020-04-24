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

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Publishable\ClassMetadataTrait;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Adds `isPublished` property on response, if not set.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use ClassMetadataTrait;

    private const ALREADY_CALLED = 'PUBLISHABLE_NORMALIZER_ALREADY_CALLED';

    private PublishableHelper $publishableHelper;
    private ManagerRegistry $registry;
    private IriConverterInterface $iriConverter;

    public function __construct(PublishableHelper $publishableHelper, ManagerRegistry $registry, IriConverterInterface $iriConverter)
    {
        $this->publishableHelper = $publishableHelper;
        $this->registry = $registry;
        $this->iriConverter = $iriConverter;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);
        $configuration = $this->publishableHelper->getConfiguration($object);

        if (!\array_key_exists('published', $data)) {
            $data['published'] = $this->publishableHelper->isPublished($object);
        }

        if (!\array_key_exists($configuration->fieldName, $data)) {
            $data[$configuration->fieldName] = $this->getClassMetadata($object)->getFieldValue($object, $configuration->fieldName);
        }

        if (!\array_key_exists($configuration->associationName, $data)) {
            $value = $this->getClassMetadata($object)->getFieldValue($object, $configuration->associationName);
            $data[$configuration->fieldName] = $value ? $this->iriConverter->getIriFromItem($value) : null;
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        if (!\is_object($data) || $data instanceof \Traversable) {
            return false;
        }

        return $this->publishableHelper->isPublishable($data);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
