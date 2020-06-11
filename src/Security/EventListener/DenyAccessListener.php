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

namespace Silverback\ApiComponentsBundle\Security\EventListener;

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Repository\Core\AbstractPageDataRepository;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;

/**
 * This will NOT restrict access to components fetched as a collection. As recomended by API Platform best practices, that should
 * be implemented in a Doctrine extension by the application developer.
 *
 * @author Daniel West <daniel@silverback.is>
 */
final class DenyAccessListener
{
    private RouteRepository $routeRepository;
    private AbstractPageDataRepository $pageDataRepository;
    private Security $security;
    private IriConverterInterface $iriConverter;
    private HttpKernelInterface $httpKernel;

    public function __construct(RouteRepository $routeRepository, AbstractPageDataRepository $pageDataRepository, Security $security, IriConverterInterface $iriConverter, HttpKernelInterface $httpKernel)
    {
        $this->routeRepository = $routeRepository;
        $this->pageDataRepository = $pageDataRepository;
        $this->security = $security;
        $this->iriConverter = $iriConverter;
        $this->httpKernel = $httpKernel;
    }

    public function onPreDeserialize(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $resource = $request->attributes->get('data');

        if ($resource instanceof AbstractComponent) {
            if ($this->isComponentAccessible($resource, $request)) {
                return;
            }
            throw new AccessDeniedException('Component access denied.');
        }

        if ($resource instanceof AbstractPageData) {
            if (false !== $this->isPageDataAllowedByRoute($resource)) {
                return;
            }
            throw new AccessDeniedException('Page data access denied.');
        }
    }

    private function isComponentAccessible(AbstractComponent $component, Request $request): bool
    {
        return true === ($isRouteAllowed = $this->isComponentAllowedByRoute($component)) ||
            true === ($isPageDataAllowed = $this->isComponentAllowedByPageDataSecurityPolicy($component, $request)) ||
            (null === $isRouteAllowed && null === $isPageDataAllowed);
    }

    private function isComponentAllowedByPageDataSecurityPolicy(AbstractComponent $component, Request $request): ?bool
    {
        $pageDataResources = $this->pageDataRepository->findByComponent($component);

        // abstain - no results to say yay or nay
        if (!\count($pageDataResources)) {
            return null;
        }

        foreach ($pageDataResources as $pageDataResource) {
            $path = $this->iriConverter->getIriFromItem($pageDataResource);

            $subRequest = Request::create(
                $path,
                Request::METHOD_GET,
                [],
                $request->cookies->all(),
                [],
                $request->server->all(),
                null
            );

            try {
                $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

                return true;
            } catch (\Exception $e) {
                if (\in_array($e->getCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true)) {
                    continue;
                }
                throw $e;
            }
        }

        return false;
    }

    private function isComponentAllowedByRoute(AbstractComponent $component): ?bool
    {
        $routes = $this->routeRepository->findByComponent($component);

        if (!\count($routes)) {
            return null;
        }

        foreach ($routes as $route) {
            if ($this->security->isGranted(RouteVoter::READ_ROUTE, $route)) {
                return true;
            }
        }

        return false;
    }

    private function isPageDataAllowedByRoute(AbstractPageData $pageData): ?bool
    {
        $routes = $this->routeRepository->findByPageData($pageData);

        // abstain - no route to check
        if (!\count($routes)) {
            return null;
        }

        foreach ($routes as $route) {
            $isGranted = $this->security->isGranted(RouteVoter::READ_ROUTE, $route);
            if ($isGranted) {
                return true;
            }
        }

        return false;
    }
}
