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

namespace Silverback\ApiComponentBundle\Metadata;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Core\AbstractPageData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class AutoRoutePrefixMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        $routePrefixParts = [];

        if (is_subclass_of($resourceClass, AbstractComponent::class)) {
            $routePrefixParts[] = 'component';
        } elseif (is_subclass_of($resourceClass, AbstractPageData::class)) {
            $routePrefixParts[] = 'page_data';
        } else {
            $reflection = new \ReflectionClass($resourceClass);
            $namespace = $reflection->getNamespaceName();
            $acbNamespace = 'Silverback\ApiComponentBundle\\';

            if (0 === strpos($namespace, $acbNamespace)) {
                $routePrefixParts[] = '_';
            }
        }

        if (!\count($routePrefixParts)) {
            return $resourceMetadata;
        }

        return $this->prefixRoute($routePrefixParts, $resourceMetadata);
    }

    private function prefixRoute(array $routePrefixParts, ResourceMetadata $resourceMetadata): ResourceMetadata
    {
        if ($currentRoutePrefix = $resourceMetadata->getAttribute('route_prefix')) {
            $routePrefixParts[] = trim($currentRoutePrefix, '/');
        }
        $newRoutePrefix = '/' . implode('/', $routePrefixParts);

        $attributes = $resourceMetadata->getAttributes() ?: [];

        return $resourceMetadata->withAttributes(array_merge($attributes, [
            'route_prefix' => $newRoutePrefix,
        ]));
    }
}
