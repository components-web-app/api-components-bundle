<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Metadata\Exception\HttpExceptionInterface as MetadataHttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class UnpublishedRouteExceptionListener
{
    public const string REQUEST_ATTRIBUTE = '_cwa_unpublished_route';

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethodCacheable() || true !== $request->attributes->get(self::REQUEST_ATTRIBUTE)) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!\in_array($this->resolveStatusCode($throwable), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true)) {
            return;
        }

        $event->setThrowable(new NotFoundHttpException('Not Found'));
    }

    private function resolveStatusCode(\Throwable $throwable): ?int
    {
        if ($throwable instanceof HttpExceptionInterface || $throwable instanceof MetadataHttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        if ($throwable instanceof AccessDeniedException) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($throwable instanceof AuthenticationException) {
            return Response::HTTP_UNAUTHORIZED;
        }

        return null;
    }
}
