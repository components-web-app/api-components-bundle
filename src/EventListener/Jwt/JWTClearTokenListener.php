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

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class JWTClearTokenListener
{
    public function __construct(
        private readonly JWTCookieProvider $cookieProvider,
        private readonly MercureAuthorization $mercureAuthorization,
        private readonly ?CwaCollectorData $collectorData = null,
    ) {
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $this->clearJwtCookie($event->getResponse());
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $this->clearJwtCookie($event->getResponse());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ('_api_me' !== $request->attributes->get('_api_operation_name')) {
            return;
        }
        $response = $event->getResponse();
        if ($response->isSuccessful()) {
            return;
        }
        // Only clear the JWT cookie if it was present in the request (not anonymous /me)
        $clearCookie = $this->cookieProvider->createCookie('x.x.x', null, 1);
        if ($request->cookies->has($clearCookie->getName())) {
            $response->headers->setCookie($clearCookie);
            $response->headers->setCookie($this->mercureAuthorization->getClearAuthorizationCookie());
        }
    }

    private function clearJwtCookie(Response $response): void
    {
        $response->headers->setCookie($this->cookieProvider->createCookie('x.x.x', null, 1));
        $response->headers->setCookie($this->mercureAuthorization->getClearAuthorizationCookie());
        $this->collectorData?->recordJwtCookieCleared();
    }
}
