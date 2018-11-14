<?php

namespace Silverback\ApiComponentBundle\Serializer;

use Silverback\ApiComponentBundle\Serializer\Middleware\MiddlewareInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    /** @var iterable|MiddlewareInterface[] */
    private $normalizerMiddleware;
    /** @var iterable|NormalizerInterface[] */
    private $normalizers;
    /** @var MiddlewareInterface[] */
    private $supportedMiddleware = [];

    public function __construct(iterable $normalizerMiddleware, iterable $normalizers)
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

        $this->supportedMiddleware = [];
        foreach ($this->normalizerMiddleware as $modifier) {
            if ($modifier->supportsData($data)) {
                $this->supportedMiddleware[] = $modifier;
            }
        }

        return !empty($this->supportedMiddleware);
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
        foreach ($this->supportedMiddleware as $modifier) {
            $modifier->process($object);
        }

        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof self) {
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
