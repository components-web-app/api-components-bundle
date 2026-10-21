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
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Realtime\ApiResource\RealtimeEvent;
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
    private const string EMPTY_QUERY = '\\?';
    private const string DRAFT_QUERY = '\\?draft=1';

    public function __construct(
        private readonly ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly RequestContext $requestContext,
        private readonly Authorization $mercureAuthorization,
        private readonly RequestStack $requestStack,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly RouterInterface $router,
        private readonly int $cookieLifetime,
        private readonly string $cookieSameSite = Cookie::SAMESITE_STRICT,
        private readonly ?string $hubName = null,
        private readonly bool $secureSubscriptions = false,
        private readonly bool $realtimeEnabled = true,
    ) {
    }

    public function getAuthorizationCookie(): Cookie
    {
        $subscribeTopics = $this->getSubscribeTopics();
        $expiresAt = new \DateTimeImmutable(\sprintf('+%d seconds', $this->cookieLifetime));

        return $this->mercureAuthorization
            ->createCookie($this->requestStack->getCurrentRequest(), ['urlpattern' => $subscribeTopics], null, ['exp' => $expiresAt], $this->hubName)
            ->withSameSite($this->cookieSameSite);
    }

    public function getClearAuthorizationCookie(): Cookie
    {
        return $this->mercureAuthorization
            ->createClearCookie($this->requestStack->getCurrentRequest(), $this->hubName)
            ->withSameSite($this->cookieSameSite);
    }

    public function getSubscribeTopics(): array
    {
        $subscribeIris = [];
        foreach ($this->resourceNameCollectionFactory->create() as $resourceClass) {
            if ($resourceIris = $this->getSubscribeIrisForResource($resourceClass)) {
                $subscribeIris[] = $resourceIris;
            }
        }

        $topics = array_merge([], ...$subscribeIris);

        if ($this->realtimeEnabled && $this->isRealtimeGranted()) {
            $topics[] = $this->getRealtimeTopic() . self::EMPTY_QUERY;
        }

        return $topics;
    }

    public function getRealtimeTopic(): string
    {
        $operation = $this->resourceMetadataCollectionFactory->create(RealtimeEvent::class)->getOperation(RealtimeEvent::OPERATION_NAME);

        if (!$operation instanceof HttpOperation) {
            throw new \LogicException(\sprintf('The real-time event resource "%s" has no HTTP operation.', RealtimeEvent::class));
        }

        return $this->buildAbsoluteUrl() . $this->getOperationUrlPattern($operation);
    }

    private function isRealtimeGranted(): bool
    {
        try {
            return $this->authorizationChecker->isGranted(new Expression(RealtimeEvent::SECURITY));
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
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

        $urlPattern = $this->buildAbsoluteUrl() . $this->getOperationUrlPattern($operation);
        $subscribeIris = [$urlPattern . self::EMPTY_QUERY];

        if ($this->publishableStatusChecker->isGranted($operation->getClass())) {
            $subscribeIris[] = $urlPattern . self::DRAFT_QUERY;
        }

        return $subscribeIris;
    }

    private function getOperationUrlPattern(HttpOperation $operation): string
    {
        $path = $this->router->getRouteCollection()->get((string) $operation->getName())?->getPath() ?? $operation->getRoutePrefix() . $operation->getUriTemplate();

        foreach ([self::SYMFONY_FORMAT_SUFFIX, self::URI_TEMPLATE_FORMAT_SUFFIX] as $formatSuffix) {
            if (str_ends_with($path, $formatSuffix)) {
                $path = substr($path, 0, -\strlen($formatSuffix));
            }
        }

        return preg_replace('/\{(\w+)\}/', ':$1', $path);
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

    private function buildAbsoluteUrl(): string
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
