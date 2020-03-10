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

use Silverback\ApiComponentBundle\ApiComponentBundleEvents;
use Silverback\ApiComponentBundle\Event\PreNormalizeEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiNormalizer implements NormalizerAwareInterface, ContextAwareNormalizerInterface, CacheableSupportsMethodInterface
{
    use NormalizerAwareTrait;
    private const ALREADY_CALLED = 'API_COMPONENT_RESOURCE_NORMALIZER_ALREADY_CALLED';

    private PropertyAccessor $propertyAccessor;
    private EventDispatcher $eventDispatcher;

    public function __construct(EventDispatcher $eventDispatcher)
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->eventDispatcher = $eventDispatcher;
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }

        return !(!\is_object($data) ||
            \in_array($this->getResourceId($data), $context[self::ALREADY_CALLED], true));
    }

    public function normalize($resource, string $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED][] = $this->getResourceId($resource);

        $event = new PreNormalizeEvent($resource);
        $this->eventDispatcher->dispatch($event, ApiComponentBundleEvents::PRE_NORMALIZE);

        return $this->normalizer->normalize($event->getResource(), $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    private function getResourceId($object)
    {
        try {
            return \get_class($object) . '/' . $this->propertyAccessor->getValue($object, 'id');
        } catch (NoSuchPropertyException $e) {
            return true;
        }
    }
}
