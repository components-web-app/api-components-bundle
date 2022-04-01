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
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Security\Voter\AbstractRoutableVoter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutableResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);

        $refl = new \ReflectionClass($resourceClass);
        if (!$refl->implementsInterface(RoutableInterface::class)) {
            return $resourceMetadata;
        }

        return $resourceMetadata;
//        $newResources = [];
//        /** @var ApiResource $resourceMetadatum */
//        foreach ($resourceMetadata as $resourceMetadatum) {
//            $newOperations = [];
//            $operations = $resourceMetadatum->getOperations();
//            if ($operations) {
//                /** @var Operation $operation */
//                foreach ($operations as $operation) {
//                    if (!$operation->getSecurity()) {
//                        $operation = $operation->withSecurity(sprintf("is_granted('%s', object)", AbstractRoutableVoter::READ_ROUTABLE));
//                    }
//                    $newOperations[] = $operation;
//                }
//            }
//            $newResources[] = $resourceMetadatum->withOperations(new Operations($newOperations));
//        }

//        return new ResourceMetadataCollection($resourceClass, $newResources);
    }
}
