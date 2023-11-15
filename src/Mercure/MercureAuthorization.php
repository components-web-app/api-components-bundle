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

namespace Silverback\ApiComponentsBundle\Mercure;

use ApiPlatform\Exception\OperationNotFoundException as LegacyOperationNotFoundException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\RequestContext;

class MercureAuthorization
{
    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly RequestContext $requestContext,
        private readonly Authorization $mercureAuthorization,
        private readonly RequestStack $requestStack,
        private readonly string $cookieSameSite = Cookie::SAMESITE_STRICT,
        private readonly ?string $hubName = null
    ) {
    }

    public function getAuthorizationCookie(): Cookie
    {
        $subscribeTopics = $this->getSubscribeTopics();
        $cookie = $this->mercureAuthorization->createCookie($this->requestStack->getCurrentRequest(), $subscribeTopics, null, [], $this->hubName);

        return $cookie
            ->withSameSite($this->cookieSameSite)
            ->withExpires(time() + (10 * 365 * 24 * 60 * 60));
    }

    public function getClearAuthorizationCookie(): Cookie
    {
        return $this->getAuthorizationCookie()->withExpires(1)->withValue('x.x.x');
    }

    public function getSubscribeTopics(): array
    {
        $subscribeIris = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            if ($resourceIris = $this->getSubscribeIrisForResource($resourceClass)) {
                $subscribeIris[] = $resourceIris;
            }
        }

        return array_merge([], ...$subscribeIris);
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
        } catch (OperationNotFoundException|LegacyOperationNotFoundException) {
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
        $defaultPort = $this->requestContext->isSecure() ? $this->requestContext->getHttpsPort() : $this->requestContext->getHttpPort();
        if (80 !== $defaultPort && 443 !== $defaultPort) {
            return sprintf('%s://%s:%d', $scheme, $host, $defaultPort);
        }

        return sprintf('%s://%s', $scheme, $host);
    }
}
