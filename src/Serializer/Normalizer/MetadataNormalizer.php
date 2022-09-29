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

use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MetadataNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const ALREADY_CALLED = 'METADATA_NORMALIZER_ALREADY_CALLED';

    private PropertyAccessor $propertyAccessor;

    public function __construct(private string $metadataKey, private ResourceMetadataInterface $resourceMetadata)
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!\is_object($data)) {
            return false;
        }
        if (!isset($context[self::ALREADY_CALLED])) {
            return false;
        }
        try {
            $id = $this->propertyAccessor->getValue($data, 'id');
        } catch (NoSuchPropertyException $e) {
            return false;
        }

        return !\in_array($id, $context[self::ALREADY_CALLED], true) &&
            $this->resourceMetadata->isInit();
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED][] = $this->propertyAccessor->getValue($object, 'id');
        $metadataContext = $context;
        unset($metadataContext['operation'], $metadataContext['operation_name'], $metadataContext['resource_class']);
        if (isset($metadataContext['groups'])) {
            $metadataContext['groups'][] = 'cwa_resource:metadata';
        } else {
            $metadataContext['groups'] = ['cwa_resource:metadata'];
        }
        $metadata = $this->resourceMetadata->getResourceMetadata();
        $metadataContext['resource_class'] = $metadata ? \get_class($metadata) : null;

        $metadata = $this->normalizer->normalize($metadata, $format, $metadataContext);
        $data = $this->normalizer->normalize($object, $format, $context);

        $data[$this->metadataKey] = empty($metadata) ? null : $metadata;

        return $data;
    }
}
