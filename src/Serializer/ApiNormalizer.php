<?php

namespace Silverback\ApiComponentBundle\Serializer;

use Silverback\ApiComponentBundle\DataModifier\DataModifierInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    /** @var iterable|DataModifierInterface[] */
    private $normalizerMiddleware;
    /** @var iterable|NormalizerInterface[] */
    private $normalizers;
    /** @var DataModifierInterface[] */
    private $supportedModifiers = [];

    public function __construct(iterable $normalizerMiddleware = [], iterable $normalizers = [])
    {
        $this->normalizerMiddleware = $normalizerMiddleware;
        $this->normalizers = $normalizers;
    }

    /**
     * Check if any of our entity normalizers should be called
     * @param mixed $data
     * @param null|string $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        if (!\is_object($data)) {
            return false;
        }

        $this->supportedModifiers = [];
        foreach ($this->normalizerMiddleware as $modifier) {
            if ($modifier->supportsData($data)) {
                $this->supportedModifiers[] = $modifier;
            }
        }

        return !empty($this->supportedModifiers);
    }

    /**
     * Here we need to call our own entity normalizer followed by the next supported normalizer
     * @param mixed $object
     * @param null|string $format
     * @param array $context
     * @return array|bool|float|int|mixed|string
     */
    public function normalize($object, $format = null, array $context = array())
    {
        foreach ($this->supportedModifiers as $modifier) {
            $modifier->process($object);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof self || !$normalizer instanceof NormalizerInterface) {
                continue;
            }
            if ($normalizer->supportsNormalization($object, $format)) {
                return $normalizer->normalize($object, $format, $context);
            }
        }

        return $object;
    }

    /**
     * @return bool
     */
    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
