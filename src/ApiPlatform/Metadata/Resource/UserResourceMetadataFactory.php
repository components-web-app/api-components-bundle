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

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;

/**
 * Adds a /me endpoint.
 *
 * @author Daniel West <daniel@silverback.is>
 *
 * @deprecated
 */
class UserResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!is_a($resourceClass, AbstractUser::class, true)) {
            return $resourceMetadata;
        }

        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $itemOperations['me'] = array_replace_recursive([], $itemOperations['get'], [
            'method' => 'GET',
            'path' => '/me.{_format}',
            'security' => 'is_granted("IS_AUTHENTICATED_FULLY")',
        ]);

        return $resourceMetadata->withItemOperations($itemOperations);
    }
}
