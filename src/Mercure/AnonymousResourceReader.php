<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Mercure;

use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Silverback\ApiComponentsBundle\Utility\CallerScopedStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class AnonymousResourceReader
{
    public const string REQUEST_ATTRIBUTE = '_cwa_anonymous_read';

    private const array DENIED_STATUSES = [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_NOT_FOUND];

    private const array CALLER_SERVER_KEYS = [
        'HTTP_COOKIE',
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'PHP_AUTH_USER',
        'PHP_AUTH_PW',
        'PHP_AUTH_DIGEST',
        'AUTH_TYPE',
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
        'HTTP_CONTENT_TYPE',
        'HTTP_CONTENT_LENGTH',
        'HTTP_IF_NONE_MATCH',
        'HTTP_IF_MATCH',
        'HTTP_IF_MODIFIED_SINCE',
        'HTTP_IF_UNMODIFIED_SINCE',
        'HTTP_X_HTTP_METHOD_OVERRIDE',
    ];

    /**
     * @param iterable<CallerScopedStateInterface> $callerScopedStates
     */
    public function __construct(
        private readonly SubRequestKernel $subRequestKernel,
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly iterable $callerScopedStates,
    ) {
    }

    public function read(string $path, string $accept): ?string
    {
        $request = $this->createRequest($path, $accept);
        $token = $this->tokenStorage->getToken();
        $restores = [];
        foreach ($this->callerScopedStates as $callerScopedState) {
            $restores[] = $callerScopedState->isolate();
        }
        $this->tokenStorage->setToken(null);

        try {
            $response = $this->subRequestKernel->handle($request);
        } catch (AccessDeniedException|AuthenticationException) {
            return null;
        } catch (HttpExceptionInterface $exception) {
            if (\in_array($exception->getStatusCode(), self::DENIED_STATUSES, true)) {
                return null;
            }

            throw $exception;
        } finally {
            $this->tokenStorage->setToken($token);
            foreach (array_reverse($restores) as $restore) {
                $restore();
            }
        }

        if (Response::HTTP_OK !== $response->getStatusCode()) {
            return null;
        }

        return (string) $response->getContent();
    }

    private function createRequest(string $path, string $accept): Request
    {
        $server = $this->requestStack->getMainRequest()?->server->all() ?? [];
        foreach (self::CALLER_SERVER_KEYS as $key) {
            unset($server[$key]);
        }
        $server['HTTP_ACCEPT'] = $accept;

        $request = Request::create($path, Request::METHOD_GET, [], [], [], $server);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, true);

        return $request;
    }
}
