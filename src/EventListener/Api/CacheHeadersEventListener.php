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
     */
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        PublishableStatusChecker $publishableStatusChecker,
        private readonly array $personalisedResourceClasses = [],
    ) {
        $this->publishableAttributeReader = $publishableStatusChecker->getAttributeReader();
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->isMethodCacheable()) {
            return;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');
        if (!\is_string($resourceClass) || !$this->isPersonalisableResource($resourceClass)) {
            return;
        }

        if (!$this->isAuthenticated()) {
            return;
        }

        $response = $event->getResponse();
        // The body may carry draft or role-specific data tied to this authenticated session, so it
        // must never be stored by a shared cache. `private` overrides API Platform's default
        // `public`; `no-store` is the authoritative marker a service worker's cacheWillUpdate drops.
        $response->setPrivate();
        $response->headers->removeCacheControlDirective('s-maxage');
        $response->headers->addCacheControlDirective('no-store');
    }

    private function isPersonalisableResource(string $resourceClass): bool
    {
        foreach ($this->personalisedResourceClasses as $affectedClass) {
            if (is_a($resourceClass, $affectedClass, true)) {
                return true;
            }
        }

        // Any resource configured as Publishable varies by auth (draft vs published) even when it is
        // an app-defined component that cannot be enumerated in the configured list above.
        return $this->publishableAttributeReader->isConfigured($resourceClass);
    }

    private function isAuthenticated(): bool
    {
        $token = $this->tokenStorage->getToken();

        return null !== $token && $token->getUser() instanceof UserInterface;
    }
}
