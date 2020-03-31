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
        if (!is_subclass_of($resourceClass, AbstractComponent::class)) {
            return $resourceMetadata;
        }
        return $this->prefixComponentRoute($resourceMetadata);
    }

    private function prefixComponentRoute(ResourceMetadata $resourceMetadata): ResourceMetadata
    {
        $routePrefixParts = ['component'];
        if ($currentRoutePrefix = $resourceMetadata->getAttribute('route_prefix')) {
            $routePrefixParts[] = trim($currentRoutePrefix, '/');
        }
        $newRoutePrefix = '/' . implode('/', $routePrefixParts);

        return $resourceMetadata->withAttributes([
            'route_prefix' => $newRoutePrefix,
        ]);
    }
}
