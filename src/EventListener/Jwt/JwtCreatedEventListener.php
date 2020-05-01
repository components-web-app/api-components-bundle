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

namespace Silverback\ApiComponentsBundle\EventListener\Jwt;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class JwtCreatedEventListener
{
    private RoleHierarchy $roleHierarchy;

    public function __construct(RoleHierarchy $roleHierarchy)
    {
        $this->roleHierarchy = $roleHierarchy;
    }

    public function updateTokenRoles(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $data = $event->getData();
        $rolesAsEntities = $user->getRoles();
        $data['roles'] = $this->roleHierarchy->getReachableRoleNames($rolesAsEntities);
        $event->setData($data);
    }
}
