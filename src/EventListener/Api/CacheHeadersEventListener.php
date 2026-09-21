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

use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Several resource responses are served from an identical URL but vary by the authenticated
 * session: a draft is returned to a permitted user and the published version to everyone else
 * (Route, ResourceManifest), and ComponentPosition rewrites its component IRI / exposes admin-only
 * groups by role. There is no distinguishing URL and no query marker, so a shared cache cannot tell
 * a public response from a personalised one.
 *
 * This listener makes that decision legible in the response itself: when an affected resource is
 * requested by an authenticated user, its response is marked `private, no-store` so no shared cache
 * (CDN, reverse proxy, or service worker) ever stores it. Anonymous requests are left on API
 * Platform's public cache headers, so the only variant a shared cache retains is the published one —
 * the same rule the edge cache already enforces by excluding cookie-bearing requests.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class CacheHeadersEventListener
{
    private readonly PublishableAttributeReader $publishableAttributeReader;

    /**
     * @param array<class-string> $personalisedResourceClasses
     * @param array<class-string> $scheduledExpiryResourceClasses
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        PublishableStatusChecker $publishableStatusChecker,
        private readonly RouteRepository $routeRepository,
        private readonly array $personalisedResourceClasses = [],
        private readonly array $scheduledExpiryResourceClasses = [],
    ) {
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->isMethodCacheable()) {
            return;
        }

        $response = $event->getResponse();

        if (true === $request->attributes->get(UnpublishedRouteExceptionListener::REQUEST_ATTRIBUTE)) {
            $this->markNeverStored($response);

            return;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        if (!\is_string($resourceClass)) {
            return;
        }

        if ($this->isAuthenticated()) {
            if ($this->isPersonalisableResource($resourceClass)) {
                $this->markNeverStored($response);
            }

            return;
        }

        $this->capAtNextPublicationChange($request, $response, $resourceClass);
    }

    private function markNeverStored(Response $response): void
    {
        $response->setPrivate();
        $response->headers->removeCacheControlDirective('s-maxage');
        $response->headers->addCacheControlDirective('no-store');
    }

    private function capAtNextPublicationChange(Request $request, Response $response, string $resourceClass): void
    {
        if (!$response->isSuccessful()) {
            return;
        }

        $sharedMaxAge = $response->headers->getCacheControlDirective('s-maxage');
        $maxAge = $response->headers->getCacheControlDirective('max-age');
        if (null === $sharedMaxAge && null === $maxAge) {
            return;
        }

        $now = new \DateTimeImmutable();
        $next = $this->findNextTransition($response, $resourceClass, $now);
        if (null === $next) {
            return;
        }

        $seconds = max(0, $next->getTimestamp() - $now->getTimestamp());

        if (null !== $sharedMaxAge && (int) $sharedMaxAge > $seconds) {
            $response->headers->addCacheControlDirective('s-maxage', (string) $seconds);
        }
        if (null !== $maxAge && (int) $maxAge > $seconds) {
            $response->headers->addCacheControlDirective('max-age', (string) $seconds);
        }
    }

    private function findNextTransition(Response $response, string $resourceClass, \DateTimeImmutable $now): ?\DateTimeInterface
    {
        $transitions = [];

        $expires = $response->getExpires();
        if ($expires && $expires > $now) {
            $transitions[] = $expires;
        }

        if ($this->isScheduledExpiryResource($resourceClass)) {
            $nextLiveAt = $this->routeRepository->findNextLiveAt($now);
            if (null !== $nextLiveAt) {
                $transitions[] = $nextLiveAt;
            }
        }

        return $transitions ? min($transitions) : null;
    }

    private function isScheduledExpiryResource(string $resourceClass): bool
    {
        foreach ($this->scheduledExpiryResourceClasses as $affectedClass) {
            if (is_a($resourceClass, $affectedClass, true)) {
                return true;
            }
        }

        return false;
    }

    private function isPersonalisableResource(string $resourceClass): bool
    {
        foreach ($this->personalisedResourceClasses as $affectedClass) {
            if (is_a($resourceClass, $affectedClass, true)) {
                return true;
            }
        }

        return $this->publishableAttributeReader->isConfigured($resourceClass);
    }

    private function isAuthenticated(): bool
    {
        $token = $this->tokenStorage->getToken();

        return null !== $token && $token->getUser() instanceof UserInterface;
    }
}
