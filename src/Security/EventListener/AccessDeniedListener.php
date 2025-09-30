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

use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Silverback\ApiComponentsBundle\EventListener\Api\ApiEventListenerTrait;
use Silverback\ApiComponentsBundle\Mercure\MercureAuthorization;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AccessDeniedListener
{
    use ApiEventListenerTrait;

    public function __construct(
        private readonly MercureAuthorization $mercureAuthorization,
    ) {
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $attributes = $this->getAttributes($request);
        if (
            !($operation = $attributes['operation'] ?? null)
            || '_api_me' !== $operation->getName()
            || !($response = $event->getResponse()) instanceof JWTAuthenticationFailureResponse) {
            return;
        }
        $response->headers->setCookie($this->mercureAuthorization->getClearAuthorizationCookie());
    }
}
