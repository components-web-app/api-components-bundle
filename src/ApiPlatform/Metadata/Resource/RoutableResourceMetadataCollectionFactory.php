<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
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

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated, private readonly ?string $securityStr = null)
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

        $newResources = [];
        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $resourceMetadatum) {
            $newOperations = [];
            $operations = $resourceMetadatum->getOperations();
            if ($operations) {
                /** @var Operation $operation */
                foreach ($operations as $i => $operation) {
                    if ($operation->getSecurity()) {
                        $newOperations[$i] = $operation;
                        continue;
                    }

                    if (HttpOperation::METHOD_POST === $operation->getMethod()) {
                        // POST (creation) — apply securityStr directly since the voter cannot
                        // check the subject pre-denormalize. No restriction if securityStr is null.
                        if ($this->securityStr) {
                            $operation = $operation->withSecurity($this->securityStr);
                        }
                    } elseif (!$operation instanceof CollectionOperationInterface) {
                        // Item operations (GET, PATCH, DELETE, PUT) — delegate to the routable voter
                        // which checks the route or falls back to securityStr.
                        $operation = $operation->withSecurity(\sprintf("is_granted('%s', object)", AbstractRoutableVoter::READ_ROUTABLE));
                    }

                    $newOperations[$i] = $operation;
                }
            }
            $newResources[] = $resourceMetadatum->withOperations(new Operations($newOperations));
        }

        return new ResourceMetadataCollection($resourceClass, $newResources);
    }
}
