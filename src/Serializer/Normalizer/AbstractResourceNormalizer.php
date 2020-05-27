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

use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AbstractResourceNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'ABSTRACT_COMPONENT_NORMALIZER_ALREADY_CALLED';

    private ApiResourceRouteFinder $routeFinder;

    public function __construct(ApiResourceRouteFinder $routeFinder)
    {
        $this->routeFinder = $routeFinder;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        try {
            return !isset($context[self::ALREADY_CALLED]) && (new \ReflectionClass($type))->isAbstract();
        } catch (\ReflectionException $exception) {
            return false;
        }
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        if (\is_string($data)) {
            $iri = $data;
        } else {
            $iri = $data['@id'] ?? null;
        }
        if ($iri) {
            $routeParameters = $this->routeFinder->findByIri($iri);
            $type = $routeParameters['_api_resource_class'];
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}
