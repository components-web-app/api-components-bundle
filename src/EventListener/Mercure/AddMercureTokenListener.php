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
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\RequestContext;

class AddMercureTokenListener
{
    use CorsTrait;

    public function __construct(private TokenFactoryInterface $tokenFactory, private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory, private ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory, private PublishableStatusChecker $publishableStatusChecker, private RequestContext $requestContext)
    {
    }

    /**
     * Sends the Mercure header on each response.
     * Probably lock this on the "/me" route.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        // Prevent issues with NelmioCorsBundle
        if ($this->isPreflightRequest($request)) {
            return;
        }

        $subscribeIris = [];
        $response = $event->getResponse();

        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);

            try {
                $operation = $resourceMetadataCollection->getOperation(forceCollection: false, httpOperation: true);
            } catch (OperationNotFoundException $e) {
                continue;
            }

            if (!$operation instanceof HttpOperation) {
                continue;
            }

            $mercure = $operation->getMercure();

            if (!$mercure) {
                continue;
            }

            $refl = new \ReflectionClass($operation->getClass());
            $isPublishable = \count($refl->getAttributes(Publishable::class));

            // TODO: the str_replace thing should be fixed inside API Platform (will be available in next patch)
            $uriTemplate = $this->buildAbsoluteUriTemplate() . $operation->getRoutePrefix() . str_replace('.{_format}', '{._format}', $operation->getUriTemplate());

            if (!$isPublishable) {
                $subscribeIris[] = $uriTemplate;
                continue;
            }

            // Note that `?draft=1` is also hard coded into the PublishableIriConverter, probably make this configurable somewhere
            if ($this->publishableStatusChecker->isGranted($operation->getClass())) {
                $subscribeIris[] = $uriTemplate . '?draft=1';
                $subscribeIris[] = $uriTemplate;
                continue;
            }

            $subscribeIris[] = $uriTemplate;
        }

        $response->headers->setCookie(Cookie::create('mercureAuthorization', $this->tokenFactory->create($subscribeIris, [])));
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
