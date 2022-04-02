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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutingPrefixResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        $routePrefixParts = [];
        if (is_subclass_of($resourceClass, AbstractComponent::class)) {
            $routePrefixParts[] = 'component';
        } elseif (is_subclass_of($resourceClass, AbstractPageData::class)) {
            $routePrefixParts[] = 'page_data';
        } else {
            // underscores for core resources
            $reflection = new \ReflectionClass($resourceClass);
            $namespace = $reflection->getNamespaceName();
            if (preg_match("/Silverback\\\\ApiComponentsBundle\\\\(?!Test)[\w]+/", $namespace)) {
                $routePrefixParts[] = '_';
            }
        }

        if (!\count($routePrefixParts)) {
            return $resourceMetadata;
        }

        return $this->prefixRoute($resourceClass, $routePrefixParts, $resourceMetadata);
    }

    private function prefixRoute(string $resourceClass, array $routePrefixParts, ResourceMetadataCollection $resourceMetadata): ResourceMetadataCollection
    {
        $resources = [];
        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $i => $resourceMetadatum) {
            if ($currentRoutePrefix = $resourceMetadatum->getRoutePrefix()) {
                $routePrefixParts[] = trim($currentRoutePrefix, '/');
            }
            $newRoutePrefix = '/' . implode('/', $routePrefixParts);
            $resources[$i] = $resourceMetadatum->withRoutePrefix($newRoutePrefix);
            $newOperations = [];
            $oldOperations = $resourceMetadatum->getOperations();
            /**
             * @var string    $key
             * @var Operation $oldOperation
             */
            foreach ($oldOperations as $key => $oldOperation) {
                $newOperations[$key] = $oldOperation->withRoutePrefix($newRoutePrefix);
            }
            $resources[$i] = $resources[$i]->withOperations(new Operations($newOperations));
        }

        return new ResourceMetadataCollection($resourceClass, $resources);
    }
}
