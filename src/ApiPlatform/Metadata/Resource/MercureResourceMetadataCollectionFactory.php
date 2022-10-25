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

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\Annotation\Publishable;

/**
 * This will add an endpoint for component resources to find out usage totals.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class MercureResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        return $this->decorated->create($resourceClass);
        //
        // $refl = new \ReflectionClass($resourceClass);
        // $isPublishable = \count($refl->getAttributes(Publishable::class));
        //
        // if (!$isPublishable) {
        //     return $resourceMetadataCollection;
        // }
        //
        // foreach ($resourceMetadataCollection as $key => $resourceMetadata) {
        //     // Do not override if it's an array
        //     if (true === $resourceMetadata->getMercure()) {
        //         $resourceMetadata = $resourceMetadata->withMercure(['private' => true]);
        //     }
        //
        //     $operations = $resourceMetadata->getOperations();
        //     foreach ($operations as $operationName => $operation) {
        //         $operations->add($operationName, $operation->withMercure($resourceMetadata->getMercure()));
        //     }
        //
        //     $resourceMetadataCollection[$key] = $resourceMetadata->withOperations($operations);
        // }
        //
        // return $resourceMetadataCollection;
    }
}
