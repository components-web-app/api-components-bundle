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

use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class MercureAuthorization
{
    private const string SYMFONY_FORMAT_SUFFIX = '.{_format}';
    private const string URI_TEMPLATE_FORMAT_SUFFIX = '{._format}';

    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly RequestContext $requestContext,
        private readonly Authorization $mercureAuthorization,
        private readonly RequestStack $requestStack,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RouterInterface $router,
        private readonly string $cookieSameSite = Cookie::SAMESITE_STRICT,
        private readonly ?string $hubName = null,
        private readonly bool $secureSubscriptions = false,
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
        return $this->getAuthorizationCookie();
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

        if ($this->secureSubscriptions && !$this->isOperationAccessible($operation)) {
            return null;
        }

        $refl = new \ReflectionClass($operation->getClass());
        $isPublishable = \count($refl->getAttributes(Publishable::class));

        $uriTemplate = $this->buildAbsoluteUriTemplate() . $this->getOperationUriTemplate($operation);
        $subscribeIris = [$uriTemplate];

        if (!$isPublishable) {
            return $subscribeIris;
        }
        if ($this->publishableStatusChecker->isGranted($operation->getClass())) {
            $subscribeIris[] = $uriTemplate . '?draft=1';
        }

        return $subscribeIris;
    }

    private function getOperationUriTemplate(HttpOperation $operation): string
    {
        $path = $this->router->getRouteCollection()->get((string) $operation->getName())?->getPath();
        if (null === $path) {
            return $operation->getRoutePrefix() . $operation->getUriTemplate();
        }

        if (str_ends_with($path, self::SYMFONY_FORMAT_SUFFIX)) {
            return substr($path, 0, -\strlen(self::SYMFONY_FORMAT_SUFFIX)) . self::URI_TEMPLATE_FORMAT_SUFFIX;
        }

        return $path;
    }

    private function isOperationAccessible(HttpOperation $operation): bool
    {
        $security = $operation->getSecurity();

        if (null === $security) {
            return true;
        }

        $securityStr = (string) $security;

        if (preg_match('/\bobject\b/', $securityStr)) {
            return true;
        }

        try {
            return $this->authorizationChecker->isGranted(new Expression($securityStr));
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    private function getMercureResourceOperation(string $resourceClass): ?HttpOperation
    {
        $resourceMetadataCollection = $this->resourceMetadataCollectionFactory->create($resourceClass);

        try {
            $operation = $resourceMetadataCollection->getOperation(forceCollection: false, httpOperation: true);
        } catch (OperationNotFoundException) {
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

    private function buildAbsoluteUriTemplate(): string
    {
        $scheme = $this->requestContext->getScheme();
        $host = $this->requestContext->getHost();
        $defaultPort = $this->requestContext->isSecure() ? $this->requestContext->getHttpsPort() : $this->requestContext->getHttpPort();
        if (80 !== $defaultPort && 443 !== $defaultPort) {
            return \sprintf('%s://%s:%d', $scheme, $host, $defaultPort);
        }

        return \sprintf('%s://%s', $scheme, $host);
    }
}
