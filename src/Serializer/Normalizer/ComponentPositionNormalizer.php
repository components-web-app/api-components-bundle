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

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

/**
 * When creating a new component position the sort value should be set if not already explicitly set in the request.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED';

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && ComponentPosition::class === $type;
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var ComponentPosition $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        $sortCollection = $object->getSortCollection();
        if (!$sortCollection) {
            return $object;
        }
        if (!isset($data['sortValue'])) {
            /** @var ComponentPosition|null $lastPosition */
            $lastPosition = $sortCollection->last();
            if ($lastPosition) {
                $nextValue = $lastPosition->sortValue + 1;
                $object->setSortValue($nextValue);
            }
        } else {
            // Update other component position sort values as well
            // Thought about putting this in an event listener
            // but ComponentPosition cal also be added as writeableLink from any component...
            // Seemed simplest implementation to put it here...
            foreach ($sortCollection as $componentPosition) {
                if ($componentPosition->sortValue >= $object->sortValue) {
                    ++$componentPosition->sortValue;
                }
            }
        }

        return $object;
    }
}
