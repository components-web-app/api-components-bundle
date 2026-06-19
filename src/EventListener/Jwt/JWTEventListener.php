<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Jwt;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\JWTRefreshedEvent;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class JWTEventListener implements ResetInterface
{
    private ?string $token = null;

    public function __construct(
        private readonly RoleHierarchy $roleHierarchy,
        private readonly JWTCookieProvider $cookieProvider,
        private readonly MercureAuthorization $mercureAuthorization,
    ) {
    }

    public function onJWTAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $responseHeaders = $event->getResponse()->headers;
        $responseHeaders->setCookie($this->mercureAuthorization->getAuthorizationCookie());
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

    public function reset(): void
    {
        $this->token = null;
    }

    public function onJWTRefreshed(JWTRefreshedEvent $event): void
    {
        $this->token = $event->getToken();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Consume and clear the token so it is never reused across requests in worker mode
        $token = $this->token;
        $this->token = null;
        if (!empty($token)) {
            $responseHeaders = $event->getResponse()->headers;
            $responseHeaders->setCookie($this->cookieProvider->createCookie($token));
            $responseHeaders->setCookie($this->mercureAuthorization->getAuthorizationCookie());
        }
    }
}
