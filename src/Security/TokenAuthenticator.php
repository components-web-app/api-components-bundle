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

namespace Silverback\ApiComponentsBundle\Security;

use Silverback\ApiComponentsBundle\Entity\User\TokenUser;
use Silverback\ApiComponentsBundle\Exception\ApiPlatformAuthenticationException;
use Silverback\ApiComponentsBundle\Exception\TokenAuthenticationException;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolverInterface;
use Symfony\Component\HttpFoundation\Request;
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
    private SerializeFormatResolverInterface $formatResolver;
    private array $tokens;

    public function __construct(Security $security, SerializeFormatResolverInterface $formatResolver, array $tokens = [])
    {
        $this->security = $security;
        $this->formatResolver = $formatResolver;
        $this->tokens = $tokens;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning false will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): bool
    {
        return !$this->security->getUser();
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
        if (null === $apiToken) {
            throw new TokenAuthenticationException('Token Authentication Required');
        }
        if (!\in_array($apiToken, $this->tokens, true)) {
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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        $this->throwApiPlatformAuthenticationException($request, strtr($exception->getMessageKey(), $exception->getMessageData()));
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null): void
    {
        $this->throwApiPlatformAuthenticationException($request, $authException ? $authException->getMessage() : 'Token Authentication Required.');
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }

    private function throwApiPlatformAuthenticationException(Request $request, string $message): void
    {
        $request->attributes->set('_api_respond', true);
        $request->attributes->set('_format', $this->formatResolver->getFormatFromRequest($request));
        throw new ApiPlatformAuthenticationException($message);
    }
}
