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

namespace Silverback\ApiComponentsBundle\Serializer\ContextBuilder;

use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\ComponentLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ComponentContextBuilder implements SerializerContextBuilderInterface
{
    private SerializerContextBuilderInterface $decorated;

    public function __construct(SerializerContextBuilderInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function createFromRequest(Request $request, bool $normalization, array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);
        if (is_a($resourceClass = $context['resource_class'], AbstractComponent::class)) {
            return $context;
        }

        $reflectionClass = new \ReflectionClass($resourceClass);
        $componentNames = [$reflectionClass->getShortName()];
        while ($parent = $reflectionClass->getParentClass()) {
            $componentNames[] = $parent->getShortName();
            $reflectionClass = $parent;
        }
        $rw = $normalization ? 'read' : 'write';
        foreach ($componentNames as $componentName) {
            $context['groups'][] = sprintf('%s:%s:%s', $componentName, ComponentLoader::GROUP_NAME, $rw);
        }

        return $context;
    }
}
