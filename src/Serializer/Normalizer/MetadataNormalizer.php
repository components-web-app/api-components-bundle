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

use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MetadataNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public const METADATA_CONTEXT = 'silverback_api_components_bundle_metadata';
    private const ALREADY_CALLED = 'METADATA_NORMALIZER_ALREADY_CALLED';

    private string $metadataKey;

    public function __construct(string $metadataKey)
    {
        $this->metadataKey = $metadataKey;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && isset($context[self::METADATA_CONTEXT]);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);
        $data[$this->metadataKey] = (array) $context[self::METADATA_CONTEXT];

        return $data;
    }
}
