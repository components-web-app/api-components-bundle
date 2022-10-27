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

namespace Silverback\ApiComponentsBundle\EventListener\Mercure;

use ApiPlatform\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Util\CorsTrait;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\RequestContext;

class AddMercureTokenListener
{
    use CorsTrait;

    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly RequestContext $requestContext,
        private readonly Authorization $mercureAuthorization,
        private readonly string $cookieSameSite = Cookie::SAMESITE_STRICT,
        private readonly ?string $hubName = null
    ) {
    }

    /**
     * Sends the Mercure header on each response.
     * Probably lock this on the "/me" route.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        // Prevent issues with NelmioCorsBundle
        if ($this->isPreflightRequest($request) || $request->attributes->get('_api_item_operation_name') !== 'me') {
            return;
        }

        $subscribeIris = [];
        $response = $event->getResponse();
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            if ($resourceIris = $this->getSubscribeIrisForResource($resourceClass)) {
                $subscribeIris[] = $resourceIris;
            }
        }
        $subscribeIris = array_merge([], ...$subscribeIris);

        // Todo: await merge of https://github.com/symfony/mercure/pull/93 to remove ability to publish any updates and set to  null
        // May also be able to await a mercure bundle update to set the cookie samesite in mercure configs
        $cookie = $this->mercureAuthorization->createCookie($request, $subscribeIris, [], [], $this->hubName);
        $cookie = $cookie->withSameSite($this->cookieSameSite);
        $response->headers->setCookie($cookie);
    }

    private function getSubscribeIrisForResource(string $resourceClass): ?array
    {
        $operation = $this->getMercureResourceOperation($resourceClass);
        if (!$operation) {
            return null;
        }

        $refl = new \ReflectionClass($operation->getClass());
        $isPublishable = \count($refl->getAttributes(Publishable::class));

        $uriTemplate = $this->buildAbsoluteUriTemplate() . $operation->getRoutePrefix() . $operation->getUriTemplate();
        $subscribeIris = [$uriTemplate];

        if (!$isPublishable) {
            return $subscribeIris;
        }

        // Note that `?draft=1` is also hard coded into the PublishableIriConverter, probably make this configurable somewhere
        if ($this->publishableStatusChecker->isGranted($operation->getClass())) {
            $subscribeIris[] = $uriTemplate . '?draft=1';
        }

        return $subscribeIris;
    }

    private function getMercureResourceOperation(string $resourceClass): ?HttpOperation
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);

        try {
            $operation = $resourceMetadataCollection->getOperation(forceCollection: false, httpOperation: true);
        } catch (OperationNotFoundException $e) {
            return null;
        }

        if (!$operation instanceof HttpOperation) {
            return null;
        }

        $mercure = $operation->getMercure();

        if (!$mercure) {
            return null;
        }

        return $operation;
    }

    /**
     * Mercure subscribe iris should be absolute
     * this code can also be found in Symfony's URL Generator
     * but as we work without a symfony route here (and we would not want to do this as its not spec-compliant)
     * we do it by hand.
     */
    private function buildAbsoluteUriTemplate(): string
    {
        $scheme = $this->requestContext->getScheme();
        $host = $this->requestContext->getHost();
        $port = $this->requestContext->isSecure() ? $this->requestContext->getHttpsPort() : $this->requestContext->getHttpPort();

        if (80 !== $port || 443 !== $port) {
            return sprintf('%s://%s:%d', $scheme, $host, $port);
        }

        return sprintf('%s://%s', $scheme, $host);
    }
}
