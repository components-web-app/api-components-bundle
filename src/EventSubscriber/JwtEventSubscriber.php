<?php

namespace Silverback\ApiComponentBundle\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class JwtEventSubscriber implements EventSubscriberInterface
{
    private $roleHierarchy;

    public function __construct(RoleHierarchy $roleHierarchy)
    {
        $this->roleHierarchy = $roleHierarchy;
    }

    public static function getSubscribedEvents(): array
    {
        return [ Events::JWT_CREATED => 'updateTokenRoles' ];
    }

    public function updateTokenRoles(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $data = $event->getData();
        $reachableRoles = $this->roleHierarchy->getReachableRoles($user->getRoles());
        $data['roles'] = array_map(function(Role $role) { return (string) $role->getRole(); }, $reachableRoles);
        $event->setData($data);
    }
}
