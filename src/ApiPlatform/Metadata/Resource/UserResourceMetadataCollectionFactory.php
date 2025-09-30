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

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UserStateProvider;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

/**
 * Adds a /me endpoint.
 *
 * @author Daniel West <daniel@silverback.is>
 */
readonly class UserResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $decorated)
    {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        if (!is_a($resourceClass, AbstractUser::class, true)) {
            return $resourceMetadataCollection;
        }

        foreach ($resourceMetadataCollection as $i => $resource) {
            $resource = $resource->withCacheHeaders([
                'public' => false,
                'shared_max_age' => 0,
                'max_age' => 0,
            ]);
            $operations = $resource->getOperations();
            foreach ($operations as $key => $operation) {
                if ($operation instanceof Get) {
                    $meOperation = $this->createMeOperation($operation);
                    $operations->add(
                        '_api_me',
                        $meOperation
                    );
                }
                $operations->add(
                    $key,
                    $operation->withCacheHeaders([
                        'public' => false,
                        'shared_max_age' => 0,
                        'max_age' => 0,
                    ])
                );
            }
            $resourceMetadataCollection[$i] = $resource->withOperations($operations);
        }

        return $resourceMetadataCollection;
    }

    private function createMeOperation(Get $operation): Operation
    {
        return new Get(
            uriTemplate: '/me{._format}',
            routePrefix: $operation->getRoutePrefix(),
            cacheHeaders: [
                'public' => false,
                'shared_max_age' => 0,
                'max_age' => 0,
            ],
            shortName: '__api_me',
            class: $operation->getClass(),
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            name: '_api_me',
            provider: UserStateProvider::class
        );
    }
}
