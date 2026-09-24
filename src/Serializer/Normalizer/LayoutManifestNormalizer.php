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

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LayoutManifestNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const string ALREADY_CALLED = 'LAYOUT_MANIFEST_NORMALIZER_ALREADY_CALLED';

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof Layout
            && !isset($context[self::ALREADY_CALLED])
            && ($context[CwaTagCollector::MANIFEST_CONTEXT_KEY] ?? false);
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        $data = $this->normalizer->normalize($object, $format, $context);
        if (!\is_array($data)) {
            return $data;
        }

        $groupContext = $context;
        unset($groupContext[self::ALREADY_CALLED], $groupContext['operation'], $groupContext['operation_name'], $groupContext['uri_variables'], $groupContext['iri'], $groupContext['item_uri_template']);
        $groupContext['resource_class'] = ComponentGroup::class;
        $groupContext['api_sub_level'] = true;

        /* @var Layout $object */
        $data['componentGroups'] = array_values(array_map(
            fn (ComponentGroup $componentGroup) => $this->normalizer->normalize($componentGroup, $format, $groupContext),
            $object->getComponentGroups()->toArray()
        ));

        return $data;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Layout::class => false];
    }
}
