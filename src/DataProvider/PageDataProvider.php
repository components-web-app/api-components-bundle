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

namespace Silverback\ApiComponentsBundle\DataProvider;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
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
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly PageDataMetadataProvider $pageDataMetadataProvider,
        private readonly ManagerRegistry $managerRegistry
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
            $object = $this->iriConverter->getResourceFromIri($path);
            if ($object instanceof AbstractPageData) {
                return $object;
            }

            return null;
        }

        return $route->getPageData();
    }

    public function findPageDataComponentMetadata(object $component): iterable
    {
        $resourceShortName = $this->getComponentShortName($component);
        if (!$resourceShortName) {
            return;
        }
        $pageDataLocations = $this->getPageDataLocations($resourceShortName);
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

    private function getPageDataLocations(string $resourceShortName): array
    {
        $pageDataMetadatas = $this->pageDataMetadataProvider->createAll();
        $pageDataLocations = [];
        foreach ($pageDataMetadatas as $pageDataMetadata) {
            $resourceProperties = $pageDataMetadata->findPropertiesByComponentShortName($resourceShortName);
            if ($resourceProperties->count() > 0) {
                $pageDataLocations[$pageDataMetadata->getResourceClass()] = $resourceProperties->map(static function (PageDataPropertyMetadata $metadata) {
                    return $metadata->getProperty();
                });
            }
        }

        return $pageDataLocations;
    }

    private function getComponentShortName(object $component): ?string
    {
        $resourceClass = $component::class;
        if ($component instanceof Proxy) {
            $em = $this->managerRegistry->getManagerForClass($resourceClass);
            if (!$em) {
                return null;
            }
            if ($classMetadata = $em->getClassMetadata($resourceClass)) {
                $resourceClass = $classMetadata->getName();
            }
        }

        /** @var ResourceMetadataCollection $metadatas */
        $metadatas = $this->resourceMetadataFactory->create($resourceClass);
        /** @var ApiResource $metadata */
        foreach ($metadatas as $metadata) {
            if ($shortName = $metadata->getShortName()) {
                return $shortName;
            }
        }

        return null;
    }
}
