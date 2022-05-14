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

namespace Silverback\ApiComponentsBundle\Security\Voter;

use ApiPlatform\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentVoter extends Voter
{
    public const READ_COMPONENT = 'read_component';

    private PageDataProvider $pageDataProvider;
    private IriConverterInterface $iriConverter;
    private HttpKernelInterface $httpKernel;
    private RequestStack $requestStack;

    public function __construct(
        PageDataProvider $pageDataProvider,
        IriConverterInterface $iriConverter,
        HttpKernelInterface $httpKernel,
        RequestStack $requestStack
    ) {
        $this->pageDataProvider = $pageDataProvider;
        $this->iriConverter = $iriConverter;
        $this->httpKernel = $httpKernel;
        $this->requestStack = $requestStack;
    }

    protected function supports($attribute, $subject): bool
    {
        return self::READ_COMPONENT === $attribute && $subject instanceof AbstractComponent;
    }

    /**
     * @param AbstractComponent $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return true;
        }

        $pagesGenerator = $this->getComponentPages($subject);
        $pages = iterator_to_array($pagesGenerator);

        // Check if accessible via any route
        $routes = $this->getComponentRoutesFromPages($pages);
        $routeCount = 0;
        foreach ($routes as $route) {
            ++$routeCount;
            if ($this->isRouteReachableResource($route, $request)) {
                return true;
            }
        }

        // check if accessible via any page data

        // 1. as a page data property
        $pageData = $this->pageDataProvider->findPageDataComponentMetadata($subject);
        $pageDataCount = 0;
        foreach ($pageData as $pageDatum) {
            foreach ($pageDatum->getPageDataResources() as $pageDataResource) {
                ++$pageDataCount;
                if ($this->isPageDataReachableResource($pageDataResource, $request)) {
                    return true;
                }
            }
        }

        // 2. as a component in the page template being used by page data
        $pageDataByPagesComponentUsedIn = $this->pageDataProvider->findPageDataResourcesByPages($pages);
        foreach ($pageDataByPagesComponentUsedIn as $pageData) {
            if ($this->isPageDataReachableResource($pageData, $request)) {
                return true;
            }
        }

        return !$routeCount && !$pageDataCount && !\count($pageDataByPagesComponentUsedIn);
    }

    private function isRouteReachableResource(Route $route, Request $request): bool
    {
        $path = $this->iriConverter->getIriFromResource($route);

        return $this->isPathReachable($path, $request);
    }

    private function isPageDataReachableResource(AbstractPageData $pageData, Request $request): bool
    {
        $path = $this->iriConverter->getIriFromResource($pageData);

        return $this->isPathReachable($path, $request);
    }

    private function isPathReachable(string $path, Request $request): bool
    {
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
                return false;
            }
            throw $e;
        }
    }

    private function getComponentPages(AbstractComponent $component): iterable
    {
        $componentPositions = $component->getComponentPositions();
        if (!\count($componentPositions)) {
            return;
        }

        foreach ($componentPositions as $componentPosition) {
            $componentCollection = $componentPosition->componentCollection;
            foreach ($componentCollection->components as $parentComponent) {
                yield from $this->getComponentPages($parentComponent);
            }
            yield from $componentCollection->pages;
        }
    }

    private function getComponentRoutesFromPages(array $pages): iterable
    {
        foreach ($pages as $page) {
            $route = $page->getRoute();
            if ($route) {
                yield $route;
            }
        }
    }
}
