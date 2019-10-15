<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer;

use Silverback\ApiComponentBundle\DataTransformer\DataTransformerInterface;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\RestrictedResourceInterface;
use Symfony\Component\Security\Core\Security;
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

    private $security;

    public function __construct(iterable $dataTransformers = [], Security $security)
    {
        $this->dataTransformers = $dataTransformers;
        $this->security = $security;
    }

    private function isRestrictedResource($data): ?RestrictedResourceInterface
    {
        if ($data instanceof RestrictedResourceInterface && !$data instanceof AbstractContent) {
            return $data;
        }
        return null;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!is_object($data) || isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        if ($this->isRestrictedResource($data)) {
            return true;
        }

        $this->supportedTransformers = [];
        foreach ($this->dataTransformers as $transformer) {
            if ($transformer->supportsTransformation($data)) {
                $this->supportedTransformers[] = $transformer;
            }
        }
        return !empty($this->supportedTransformers);
    }

    private function rolesVote(iterable $roles): bool
    {
        $negativeRoles = [];
        $positiveRoles = [];
        foreach ($roles as $role) {
            if (strpos($role, '!') === 0) {
                $negativeRoles[] = substr($role, 1);
                continue;
            }
            $positiveRoles[] = $role;
        }
        $positivePass = count($positiveRoles) && $this->security->isGranted($positiveRoles);
        $negativePass = count($negativeRoles) && !$this->security->isGranted($negativeRoles);
        return $positivePass || $negativePass;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        if (
            ($restrictedResource = $this->isRestrictedResource($object)) &&
            ($roles = $restrictedResource->getSecurityRoles()) !== null &&
            !$this->rolesVote($roles)
        ) {
            return null;
        }

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
