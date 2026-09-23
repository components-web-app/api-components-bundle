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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentGroupNormalizer implements NormalizerInterface, NormalizerAwareInterface, DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_GROUP_NORMALIZER_ALREADY_CALLED';

    public function __construct(private readonly IriConverterInterface $iriConverter)
    {
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ComponentGroup
            && !isset($context[self::ALREADY_CALLED])
            && null !== $data->allowedComponents;
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var ComponentGroup $object */
        $allowed = $object->allowedComponents;
        $original = $object->componentPositions;

        $object->componentPositions = new ArrayCollection(array_values($original->filter(
            function (ComponentPosition $position) use ($allowed): bool {
                if (!$position->component) {
                    return true;
                }
                $class = $position->component::class;
                $iri = $this->iriConverter->getIriFromResource(
                    $class,
                    UrlGeneratorInterface::ABS_PATH,
                    (new GetCollection())->withClass($class),
                );

                return \in_array($iri, $allowed, true);
            }
        )->toArray()));

        $result = $this->normalizer->normalize($object, $format, $context);
        $object->componentPositions = $original;

        return $result;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return ComponentGroup::class === $type && !isset($context[self::ALREADY_CALLED]);
    }

    public function denormalize($data, $type, $format = null, array $context = []): ComponentGroup
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var ComponentGroup $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (null !== $object->allowedComponents) {
            $object->allowedComponents = array_values(array_map(
                function (string $value): string {
                    if (!str_contains($value, '\\')) {
                        return $value;
                    }

                    return $this->iriConverter->getIriFromResource(
                        $value,
                        UrlGeneratorInterface::ABS_PATH,
                        (new GetCollection())->withClass($value),
                    );
                },
                $object->allowedComponents,
            ));
        }

        return $object;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ComponentGroup::class => false];
    }
}
