<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Metadata\PageDataComponentMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataProvider
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouteRepository $routeRepository,
        private readonly IriConverterInterface $iriConverter,
        private readonly PageDataMetadataProvider $pageDataMetadataProvider,
        private readonly ManagerRegistry $managerRegistry,
    ) {
    }

    public function getOriginalRequestPath(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }
        $path = $request->headers->get('path');
        if (!$path) {
            throw new UnprocessableEntityHttpException('Could not find path header to retrieve page data');
        }

        return parse_url($path, \PHP_URL_PATH);
    }

    public function getPageData(): ?AbstractPageData
    {
        $path = $this->getOriginalRequestPath();
        if (!$path) {
            return null;
        }

        $route = $this->routeRepository->findOneByIdOrPath($path);
        if (!$route) {
            try {
                $object = $this->iriConverter->getResourceFromIri($path);
            } catch (\Exception) {
                return null;
            }
            if ($object instanceof AbstractPageData) {
                return $object;
            }

            return null;
        }

        return $route->getPageData();
    }

    public function findPageDataComponentMetadata(object $component): iterable
    {
        $componentClass = $this->getComponentClass($component);
        if (!$componentClass) {
            return;
        }
        $pageDataLocations = $this->getPageDataLocations($componentClass);
        foreach ($pageDataLocations as $pageDataClassName => $properties) {
            if ($metadata = $this->findPageDataResourcesByPropertiesAndComponent($pageDataClassName, $properties, $component)) {
                yield $metadata;
            }
        }
    }

    public function findPageDataResourcesByPages(iterable $pages): array
    {
        $em = $this->managerRegistry->getManagerForClass(AbstractPageData::class);
        if (!$em instanceof EntityManager) {
            return [];
        }
        $qb = $em->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('pd')
            ->from(AbstractPageData::class, 'pd');
        foreach ($pages as $x => $page) {
            $paramName = 'page_' . $x;
            $qb->setParameter($paramName, $page);
            $qb->orWhere($expr->eq('pd.page', ":$paramName"));
        }

        return $qb->getQuery()->getResult() ?: [];
    }

    private function findPageDataResourcesByPropertiesAndComponent(string $pageDataClassName, ArrayCollection $properties, object $component): ?PageDataComponentMetadata
    {
        $em = $this->managerRegistry->getManagerForClass($pageDataClassName);
        if (!$em instanceof EntityManager) {
            return null;
        }
        $qb = $em->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('pd')
            ->from($pageDataClassName, 'pd')
            ->setParameter('component', $component);
        foreach ($properties as $property) {
            $qb->orWhere($expr->eq('pd.' . $property, ':component'));
        }

        return new PageDataComponentMetadata($qb->getQuery()->getResult() ?: [], $properties);
    }

    private function getPageDataLocations(string $componentClass): array
    {
        $pageDataMetadatas = $this->pageDataMetadataProvider->createAll();
        $pageDataLocations = [];
        foreach ($pageDataMetadatas as $pageDataMetadata) {
            $resourceProperties = $pageDataMetadata->findPropertiesByComponentClass($componentClass);
            if ($resourceProperties->count() > 0) {
                $pageDataLocations[$pageDataMetadata->getResourceClass()] = $resourceProperties->map(static function (PageDataPropertyMetadata $metadata) {
                    return $metadata->getProperty();
                });
            }
        }

        return $pageDataLocations;
    }

    private function getComponentClass(object $component): ?string
    {
        $resourceClass = $component::class;
        if ($component instanceof Proxy) {
            $em = $this->managerRegistry->getManagerForClass($resourceClass);
            if (!$em) {
                return null;
            }
            $resourceClass = $em->getClassMetadata($resourceClass)->getName();
        }

        return $resourceClass;
    }
}
