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

use Silverback\ApiComponentsBundle\ApiResource\RouteChildren;
use Silverback\ApiComponentsBundle\ApiResource\RouteChildrenNode;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteChildrenNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = []): array
    {
        return ['children' => array_map([$this, 'normalizeNode'], $object->children)];
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return $data instanceof RouteChildren;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [RouteChildren::class => true];
    }

    private function normalizeNode(RouteChildrenNode $node): array
    {
        return [
            'route' => $node->route,
            'path' => $node->path,
            'children' => array_map([$this, 'normalizeNode'], $node->children),
        ];
    }
}
