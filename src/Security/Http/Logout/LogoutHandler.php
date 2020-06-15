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

namespace Silverback\ApiComponentsBundle\Security\Http\Logout;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Cookie\JWTCookieProvider;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;

/**
 * @deprecated As of Symfony 5.1 implement an event listener instead. Will be removed when supported symfony versions >=5.1
 */
final class LogoutHandler implements LogoutHandlerInterface
{
    private RefreshTokenStorageInterface $storage;
    private JWTCookieProvider $cookieProvider;

    public function __construct(RefreshTokenStorageInterface $storage, JWTCookieProvider $cookieProvider)
    {
        $this->storage = $storage;
        $this->cookieProvider = $cookieProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(Request $request, Response $response, TokenInterface $token): void
    {
        $this->storage->expireAll($token->getUser());
        $response->headers->setCookie($this->cookieProvider->createCookie('.', null, time()));
        $response->headers->remove('Location');
        $response->setStatusCode(Response::HTTP_OK)->setContent('');
    }
}
