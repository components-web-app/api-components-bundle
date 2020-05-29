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
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class JWTEventListener
{
    private RoleHierarchy $roleHierarchy;
    private JWTCookieProvider $cookieProvider;
    private ?string $token = null;

    public function __construct(RoleHierarchy $roleHierarchy, JWTCookieProvider $cookieProvider)
    {
        $this->cookieProvider = $cookieProvider;
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

    public function onJWTRefreshed(JWTRefreshedEvent $event): void
    {
        $this->token = $event->getToken();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!empty($this->token)) {
            $event->getResponse()->headers->setCookie($this->cookieProvider->createCookie($this->token));
        }
    }
}
