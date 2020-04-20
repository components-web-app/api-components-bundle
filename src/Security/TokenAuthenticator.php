<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Security;

use Silverback\ApiComponentBundle\Entity\User\TokenUser;
use Silverback\ApiComponentBundle\Exception\TokenAuthenticationException;
use Silverback\ApiComponentBundle\Factory\ResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TokenAuthenticator extends AbstractGuardAuthenticator
{
    private Security $security;
    private ResponseFactory $responseFactory;
    private array $tokens;

    public function __construct(Security $security, ResponseFactory $responseFactory, array $tokens = [])
    {
        $this->security = $security;
        $this->responseFactory = $responseFactory;
        $this->tokens = $tokens;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): bool
    {
        if ($this->security->getUser()) {
            return false;
        }

        return $request->headers->has('X-AUTH-TOKEN');
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request): array
    {
        return [
            'token' => $request->headers->get('X-AUTH-TOKEN'),
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider = null): ?TokenUser
    {
        $apiToken = $credentials['token'];
        if (null === $apiToken || !\in_array($apiToken, $this->tokens, true)) {
            throw new TokenAuthenticationException('The authentication token provided in the X-AUTH-TOKEN header is invalid');
        }

        return new TokenUser();
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): void
    {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return $this->responseFactory->create($request, $data, Response::HTTP_FORBIDDEN);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        $data = [
            'message' => 'Token Authentication Required',
        ];

        return $this->responseFactory->create($request, $data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}
