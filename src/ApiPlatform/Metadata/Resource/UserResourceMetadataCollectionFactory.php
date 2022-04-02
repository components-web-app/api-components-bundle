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
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

/**
 * Adds a /me endpoint.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UserResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!is_a($resourceClass, AbstractUser::class, true)) {
            return $resourceMetadata;
        }

        $newResources = [];
        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $resourceMetadatum) {
            $operations = $resourceMetadatum->getOperations();
            if ($operations) {
                foreach ($operations as $operation) {
                    if ($operation instanceof Get) {
                        $operations->add('me', $operation->withUriTemplate('/me.{_format}')->withSecurity('is_granted("IS_AUTHENTICATED_FULLY")'));
                    }
                }
            }
            $newResources[] = $resourceMetadatum;
        }

        return new ResourceMetadataCollection($resourceClass, $newResources);
    }
}
