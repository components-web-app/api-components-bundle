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
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LogoutListener
{
    private RefreshTokenStorageInterface $storage;
    private JWTCookieProvider $cookieProvider;

    public function __construct(RefreshTokenStorageInterface $storage, JWTCookieProvider $cookieProvider)
    {
        $this->storage = $storage;
        $this->cookieProvider = $cookieProvider;
    }

    public function __invoke(LogoutEvent $event): void
    {
        $this->storage->expireAll($event->getToken()->getUser());
        $response = $event->getResponse() ?? new Response();
        $response->headers->setCookie($this->cookieProvider->createCookie('.', null, time()));
        $response->headers->remove('Location');
        $response->setStatusCode(Response::HTTP_OK)->setContent('');
    }
}
