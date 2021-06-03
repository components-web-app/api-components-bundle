<?php

namespace Silverback\ApiComponentBundle\Security;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentBundle\Entity\User\User;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider as BaseUserProvider;

class EntityUserProvider extends BaseUserProvider
{
    public function __construct(
        ManagerRegistry $registry,
        string $classOrAlias = User::class,
        string $property = 'username',
        string $managerName = null
    ) {
        parent::__construct($registry, $classOrAlias, $property, $managerName);
    }
}
