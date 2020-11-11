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

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class JWTInvalidEventListener
{
    private JWTCookieProvider $cookieProvider;

    public function __construct(
        JWTCookieProvider $cookieProvider
    ) {
        $this->cookieProvider = $cookieProvider;
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->setCookie($this->cookieProvider->createCookie('x.x.x', null, time() + 1));
    }
}
