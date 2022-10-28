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

namespace Silverback\ApiComponentsBundle\Security\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LogoutListener
{
    public function __construct(
        private readonly RefreshTokenStorageInterface $storage,
        private readonly JWTCookieProvider $cookieProvider,
        private readonly MercureAuthorization $mercureAuthorization
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $this->storage->expireAll($event->getToken()->getUser());
        $response = $event->getResponse() ?? new Response();
        $response->headers->setCookie($this->cookieProvider->createCookie('x.x.x', null, 1));
        $response->headers->setCookie($this->mercureAuthorization->getClearAuthorizationCookie());
        $response->headers->remove('Location');
        $response->setStatusCode(Response::HTTP_OK)->setContent('');
        $event->setResponse($response);
    }
}
