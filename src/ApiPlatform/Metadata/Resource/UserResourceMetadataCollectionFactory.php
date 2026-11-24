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

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UserStateProvider;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Security\AdminRoles;
use Silverback\ApiComponentsBundle\Security\Voter\UserManagementVoter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
readonly class UserResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public const string OPT_OUT_EXTRA_PROPERTY = 'user_admin_security';

    private const string ME_OPERATION_NAME = '_api_me';
    private const string SELF = '(user and object.getId() == user.getId())';

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
                        self::ME_OPERATION_NAME,
                        $meOperation
                    );
                }
                if ($operation instanceof Put) {
                    $operation = $operation->withExtraProperties([...$operation->getExtraProperties(), 'standard_put' => false]);
                }
                if (self::ME_OPERATION_NAME !== $key) {
                    $operation = $this->withUserAdminSecurity($operation);
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

    private function withUserAdminSecurity(HttpOperation $operation): HttpOperation
    {
        if (false === ($operation->getExtraProperties()[self::OPT_OUT_EXTRA_PROPERTY] ?? true)) {
            return $operation;
        }

        $manage = \sprintf("(%s and is_granted('%s', object))", AdminRoles::USER_ADMIN_SECURITY, UserManagementVoter::MANAGE_USER);
        $security = match (true) {
            $operation instanceof GetCollection, $operation instanceof Post => AdminRoles::USER_ADMIN_SECURITY,
            $operation instanceof Get => AdminRoles::USER_ADMIN_SECURITY . ' or ' . self::SELF,
            $operation instanceof Patch, $operation instanceof Put => $manage . ' or ' . self::SELF,
            $operation instanceof Delete => $manage,
            default => null,
        };
        if (null === $security) {
            return $operation;
        }

        $own = $operation->getSecurity();
        if (null === $own || '' === $own) {
            return $operation->withSecurity($security);
        }

        return $operation->withSecurity(\sprintf('(%s) and (%s)', $security, $own));
    }

    private function createMeOperation(Get $operation): Operation
    {
        return new Get(
            uriTemplate: '/me{._format}',
            uriVariables: [],
            routePrefix: $operation->getRoutePrefix(),
            cacheHeaders: [
                'public' => false,
                'shared_max_age' => 0,
                'max_age' => 0,
            ],
            shortName: $operation->getShortName(),
            class: $operation->getClass(),
            security: 'is_granted("IS_AUTHENTICATED_FULLY")',
            priority: 1000,
            name: self::ME_OPERATION_NAME,
            controller: $operation->getController(),
            provider: UserStateProvider::class
        );
    }
}
