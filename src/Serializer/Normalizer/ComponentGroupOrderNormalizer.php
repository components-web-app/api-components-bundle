<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ComponentGroupOrderNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'COMPONENT_GROUP_ORDER_NORMALIZER_ALREADY_CALLED';

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ComponentGroupOrder && !isset($context[self::ALREADY_CALLED]);
    }

    /**
     * @param ComponentGroupOrder $data
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        if (isset($context['resources'])) {
            foreach (array_keys($data->sortValues) as $iri) {
                $context['resources'][$iri] = $iri;
            }
        }

        $normalized = $this->normalizer->normalize($data, $format, $context);
        if (\is_array($normalized)) {
            $normalized['sortValues'] = new \ArrayObject($data->sortValues);
        }

        return $normalized;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ComponentGroupOrder::class => false];
    }
}
