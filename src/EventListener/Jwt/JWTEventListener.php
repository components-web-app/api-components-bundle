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
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class JWTEventListener
{
    private ?string $token = null;

    public function __construct(
        private readonly RoleHierarchy $roleHierarchy,
        private readonly JWTCookieProvider $cookieProvider,
        private readonly MercureAuthorization $mercureAuthorization
    ) {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        /** @var AbstractUser $user */
        $user = $event->getUser();
        $data = $event->getData();
        $rolesAsEntities = $user->getRoles();
        $data['roles'] = $this->roleHierarchy->getReachableRoleNames($rolesAsEntities);
        $data['id'] = $user->getId();
        $data['emailAddress'] = $user->getEmailAddress();
        $data['emailAddressVerified'] = $user->isEmailAddressVerified();
        $data['newEmailAddress'] = $user->getNewEmailAddress();

        $event->setData($data);
    }

    public function onJWTRefreshed(JWTRefreshedEvent $event): void
    {
        $this->token = $event->getToken();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!empty($this->token)) {
            $responseHeaders = $event->getResponse()->headers;
            $responseHeaders->setCookie($this->cookieProvider->createCookie($this->token));
            $responseHeaders->setCookie($this->mercureAuthorization->getAuthorizationCookie());
        }
    }
}
