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

namespace Silverback\ApiComponentsBundle\OpenApi;

use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Metadata\Resource\ResourceNameCollection;
use PackageVersions\Versions;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class OpenApiDecorator implements NormalizerInterface
{
    private NormalizerInterface $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        if (!$object instanceof Documentation) {
            throw new InvalidArgumentException(sprintf('%s only supports %s', self::class, Documentation::class));
        }

        // We should prevent normalization for the FormInterface (Symfony) class. get the `Class elf does not exist` error
        // This currently removed the Form component from the docs... Not ideal!
        // See: https://github.com/api-platform/core/issues/3344
        $resourceNameCollection = $object->getResourceNameCollection();
        $classes = [];
        $unsupported = [Form::class, AbstractComponent::class];
        foreach ($resourceNameCollection->getIterator() as $className) {
            if (\in_array($className, $unsupported, true)) {
                continue;
            }
            $classes[] = $className;
        }
        $newResourceNameCollection = new ResourceNameCollection($classes);
        $version = sprintf('%s (%s)', $object->getVersion(), Versions::getVersion('silverbackis/api-components-bundle'));
        $newDocumentation = new Documentation($newResourceNameCollection, $object->getTitle(), $object->getDescription(), $version, $object->getMimeTypes());

        return $this->decorated->normalize($newDocumentation, $format, $context);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
