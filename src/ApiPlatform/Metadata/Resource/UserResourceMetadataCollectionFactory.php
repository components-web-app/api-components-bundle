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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UserStateProvider;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

/**
 * Adds a /me endpoint.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UserResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private readonly ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!is_a($resourceClass, AbstractUser::class, true)) {
            return $resourceMetadata;
        }

        $input = [];
        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $resourceMetadatum) {
            $operations = $resourceMetadatum->getOperations();
            if ($operations) {
                $getOperation = $this->findGetOperation($operations);
                if ($getOperation) {
                    $newOperations = [
                        $this->createMeOperation($getOperation),
                    ];
                    foreach ($newOperations as $newOperation) {
                        $operations->add($newOperation->getName(), $newOperation);
                    }
                }
            }
            $input[] = $resourceMetadatum;
        }

        return new ResourceMetadataCollection($resourceClass, $input);
    }

    private function findGetOperation(Operations $operations): ?Get
    {
        foreach ($operations as $operation) {
            if ($operation instanceof Get) {
                return $operation;
            }
        }

        return null;
    }

    private function createMeOperation(Get $operation): Operation
    {
        return (new HttpOperation(HttpOperation::METHOD_GET, '/me{._format}'))
            ->withName('me')
            ->withShortName($operation->getShortName())
            ->withClass($operation->getClass())
            ->withSecurity('is_granted("IS_AUTHENTICATED_FULLY")')
            ->withProvider(UserStateProvider::class);
    }
}
