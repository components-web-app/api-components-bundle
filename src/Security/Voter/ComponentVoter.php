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

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentVoter extends Voter
{
    use ClassMetadataTrait;

    public const READ_COMPONENT = 'read_component';

    public function __construct(
        private readonly PageDataProvider $pageDataProvider,
        private readonly IriConverterInterface $iriConverter,
        private readonly HttpKernelInterface $httpKernel,
        private readonly RequestStack $requestStack,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry
    ) {
        $this->initRegistry($registry);
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

        $subject = $this->getPublishedSubject($subject);

        $pagesGenerator = $this->getComponentPages($subject);
        $pages = iterator_to_array($pagesGenerator);

        // 1. Check if accessible via any route
        $routeVoteResult = $this->voteByRoute($pages, $request);
        if ($routeVoteResult) {
            return true;
        }

        // 2. as a page data property
        $pageDataResult = $this->voteByPageData($subject, $request);
        if ($pageDataResult) {
            return true;
        }

        // 3. as a component in the page template being used by page data
        $pageTemplateResult = $this->voteByPageTemplate($pages, $request);
        if ($pageTemplateResult) {
            return true;
        }

        // vote is ok if all sub votes abstain
        return null === $routeVoteResult && null === $pageDataResult && null === $pageTemplateResult;
    }

    private function voteByPageTemplate($pages, Request $request): ?bool
    {
        if (!\count($pages)) {
            return null;
        }
        $pageDataByPagesComponentUsedIn = $this->pageDataProvider->findPageDataResourcesByPages($pages);
        foreach ($pageDataByPagesComponentUsedIn as $pageData) {
            if ($this->isPageDataReachableResource($pageData, $request)) {
                return true;
            }
        }

        return \count($pageDataByPagesComponentUsedIn) ? false : null;
    }

    private function voteByPageData($subject, Request $request): ?bool
    {
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

        return $pageDataCount ? false : null;
    }

    private function voteByRoute($pages, Request $request): ?bool
    {
        $routes = $this->getComponentRoutesFromPages($pages);
        $routeCount = 0;
        foreach ($routes as $route) {
            ++$routeCount;
            if ($this->isRouteReachableResource($route, $request)) {
                return true;
            }
        }

        return $routeCount ? false : null;
    }

    private function getPublishedSubject($subject)
    {
        // is a draft publishable. If a published version is available we should be checking the published version to see if it is in an accessible location
        $publishableAttributeReader = $this->publishableStatusChecker->getAttributeReader();
        if ($publishableAttributeReader->isConfigured($subject) && !$this->publishableStatusChecker->isActivePublishedAt($subject)) {
            $configuration = $publishableAttributeReader->getConfiguration($subject);
            $classMetadata = $this->getClassMetadata($subject);

            $publishedResourceAssociation = $classMetadata->getFieldValue($subject, $configuration->associationName);
            if ($publishedResourceAssociation) {
                return $publishedResourceAssociation;
            }
        }

        return $subject;
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
        $serverVars = $request->server->all();
        if (isset($serverVars['HTTP_ACCEPT'])) {
            $serverVars['HTTP_ACCEPT'] = 'application/ld+json,application/json,text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        }
        $subRequest = Request::create(
            $path,
            Request::METHOD_GET,
            [],
            $request->cookies->all(),
            [],
            $serverVars,
            null
        );

        try {
            $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);

            return true;
        } catch (\Exception $e) {
            // unsupported format requested
            if ($e instanceof NotEncodableValueException) {
                return false;
            }
            if (\in_array($e->getCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], true)) {
                return false;
            }
            throw $e;
        }
    }

    private function getComponentPages(AbstractComponent $component): \Traversable
    {
        $componentPositions = $component->getComponentPositions();
        if (!\count($componentPositions)) {
            return;
        }

        foreach ($componentPositions as $componentPosition) {
            $componentGroup = $componentPosition->componentGroup;
            foreach ($componentGroup->layouts as $layout) {
                yield from $layout->pages;
            }
            foreach ($componentGroup->components as $parentComponent) {
                yield from $this->getComponentPages($parentComponent);
            }
            yield from $componentGroup->pages;
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
