<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer;

use Silverback\ApiComponentBundle\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Security\RestrictedResourceVoter;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
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

    private $propertyAccessor;
    private $restrictedResourceVoter;

    public function __construct(iterable $dataTransformers = [], RestrictedResourceVoter $restrictedResourceVoter)
    {
        $this->dataTransformers = $dataTransformers;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->restrictedResourceVoter = $restrictedResourceVoter;
    }

    private function getId($object)
    {
        try {
            return $this->propertyAccessor->getValue($object, 'id');
        } catch (NoSuchPropertyException $e) {
            return true;
        }
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }
        $this->supportedTransformers = [];
        if (!is_object($data) || in_array($this->getId($data), $context[self::ALREADY_CALLED], true)) {
            return false;
        }

        foreach ($this->dataTransformers as $transformer) {
            if ($transformer->supportsTransformation($data)) {
                $this->supportedTransformers[] = $transformer;
            }
        }

        if ($data instanceof AbstractComponent) {
            return true;
        }

        if ($this->restrictedResourceVoter->isSupported($data)) {
            return true;
        }

        return !empty($this->supportedTransformers);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        if (!$this->restrictedResourceVoter->vote($object)) {
            return null;
        }
        $context[self::ALREADY_CALLED][] = $this->getId($object);
        if ($object instanceof AbstractComponent || $object instanceof DynamicContent) {
            $context['groups'] = array_map(static function($grp) {
                if (strpos($grp, 'route') === 0) {
                    return str_replace('route', 'component', $grp);
                }
                return $grp;
            }, $context['groups']);
        }
        foreach ($this->supportedTransformers as $transformer) {
            $transformer->transform($object, $context);
        }
        return $this->normalizer->normalize($object, $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
