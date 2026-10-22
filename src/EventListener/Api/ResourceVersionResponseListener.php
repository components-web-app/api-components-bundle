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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ResourceVersionResponseListener
{
    private const array REQUEST_ONLY_SERVER_KEYS = [
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
        'HTTP_CONTENT_TYPE',
        'HTTP_CONTENT_LENGTH',
        'HTTP_IF_MATCH',
        'HTTP_IF_NONE_MATCH',
        'HTTP_IF_MODIFIED_SINCE',
        'HTTP_IF_UNMODIFIED_SINCE',
        'HTTP_X_HTTP_METHOD_OVERRIDE',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ManagerRegistry $registry,
        private SubRequestKernel $subRequestKernel,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $data = $event->getRequest()->attributes->get('data');
        if (
            !$data instanceof VersionedInterface
            || !$response->isSuccessful()
            || Response::HTTP_NO_CONTENT === $response->getStatusCode()
            || !$this->tokenStorage->getToken()?->getUser() instanceof UserInterface
        ) {
            return;
        }

        $response->setEtag((string) $data->getResourceVersion());
        $this->markNeverStored($response);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof ResourceVersionConflictException) {
            return;
        }

        foreach ($this->registry->getManagers() as $name => $manager) {
            if (!$manager instanceof EntityManagerInterface || $manager->isOpen()) {
                $manager->clear();
                continue;
            }
            $this->registry->resetManager($name);
        }

        try {
            $response = $this->subRequestKernel->handle($this->currentResourceRequest($event->getRequest()));
        } catch (HttpExceptionInterface $exception) {
            $event->setThrowable($exception);

            return;
        }

        $response->setStatusCode(Response::HTTP_PRECONDITION_FAILED);
        $this->markNeverStored($response);
        $event->setResponse($response);
    }

    private function markNeverStored(Response $response): void
    {
        $response->setPrivate();
        $response->headers->removeCacheControlDirective('s-maxage');
        $response->headers->addCacheControlDirective('no-store');
    }

    private function currentResourceRequest(Request $request): Request
    {
        $server = $request->server->all();
        foreach (self::REQUEST_ONLY_SERVER_KEYS as $key) {
            unset($server[$key]);
        }

        return Request::create($request->getBaseUrl() . $request->getPathInfo(), Request::METHOD_GET, $request->query->all(), $request->cookies->all(), [], $server);
    }
}
