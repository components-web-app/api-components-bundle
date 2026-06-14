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

use Silverback\ApiComponentsBundle\ApiResource\ResourceManifest;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait\ManifestDepthGroupTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ResourceManifestNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use ManifestDepthGroupTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'RESOURCE_MANIFEST_NORMALIZER_ALREADY_CALLED';

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        $normalized = $this->normalizer->normalize($object->entity, $format, $context);

        // AP3 may generate @id from the manifest URI template; rewrite to the canonical route IRI
        if (isset($normalized['@id']) && str_contains($normalized['@id'], '/resource_manifest/')) {
            $normalized['@id'] = str_replace('/resource_manifest/', '/routes/', $normalized['@id']);
        }

        return ['resource_iris' => $this->buildDepthGroups($normalized)];
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof ResourceManifest;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ResourceManifest::class => false];
    }
}
