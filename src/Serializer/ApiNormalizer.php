<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer;

use Silverback\ApiComponentBundle\DataTransformer\DataTransformerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class ApiNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface, CacheableSupportsMethodInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'API_COMPONENT_BUNDLE_NORMALIZER_ALREADY_CALLED';

    /** @var iterable|DataTransformerInterface[] */
    private $dataTransformers;

    /** @var DataTransformerInterface[] */
    private $supportedTransformers = [];

    public function __construct(iterable $dataTransformers = [])
    {
        $this->dataTransformers = $dataTransformers;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!is_object($data) || isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        $this->supportedTransformers = [];
        foreach ($this->dataTransformers as $transformer) {
            if ($transformer->supportsTransformation($data)) {
                $this->supportedTransformers[] = $transformer;
            }
        }
        return !empty($this->supportedTransformers);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        foreach ($this->supportedTransformers as $transformer) {
            $transformer->transform($object, $context);
        }
        $context[self::ALREADY_CALLED] = true;

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
