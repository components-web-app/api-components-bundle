<?php

namespace Silverback\ApiComponentsBundle\Security\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Silverback\ApiComponentsBundle\EventListener\Api\ApiEventListenerTrait;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedListener
{
    use ApiEventListenerTrait;

    public function __construct(
        private readonly MercureAuthorization $mercureAuthorization
    ) {
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $this->getAttributes($request);
        if ($attributes['operation']->getName() !== 'me' || !($response = $event->getResponse()) instanceof JWTAuthenticationFailureResponse) {
            return;
        }
        $response->headers->setCookie($this->mercureAuthorization->getClearAuthorizationCookie());
    }
}
