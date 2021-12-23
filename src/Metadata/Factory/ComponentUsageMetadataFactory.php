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

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageMetadataFactory
{
    private ResourceMetadataFactoryInterface $resourceMetadataFactory;
    private ComponentPositionRepository $componentPositionRepository;
    private ManagerRegistry $managerRegistry;
    private PropertyAccessor $propertyAccessor;
    private PageDataMetadataProvider $pageDataMetadataProvider;

    public function __construct(
        ResourceMetadataFactoryInterface $resourceMetadataFactory,
        PageDataMetadataProvider $pageDataMetadataProvider,
        ComponentPositionRepository $componentPositionRepository,
        ManagerRegistry $managerRegistry
    ) {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->pageDataMetadataProvider = $pageDataMetadataProvider;
        $this->componentPositionRepository = $componentPositionRepository;
        $this->managerRegistry = $managerRegistry;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function create(ComponentInterface $component): ComponentUsageMetadata
    {
        $componentPositions = $this->componentPositionRepository->findByComponent($component);
        $componentPositionCount = \count($componentPositions);

        $pageDataCount = $this->getPageDataTotal($component);

        return new ComponentUsageMetadata($componentPositionCount, $pageDataCount);
    }

    private function getPageDataTotal(ComponentInterface $component): ?int
    {
        // we want the SHORT NAME
        $resourceClass = \get_class($component);
        if ($component instanceof Proxy) {
            $em = $this->managerRegistry->getManagerForClass($resourceClass);
            if (!$em) {
                return null;
            }
            $resourceClass = $em->getClassMetadata($resourceClass)->getName();
        }
        $apiPlatformMetadata = $this->resourceMetadataFactory->create($resourceClass);
        $resourceShortName = $apiPlatformMetadata->getShortName();

        $pageDataLocations = $this->getPageDataLocations($resourceShortName);
        $pageDataCount = 0;
        foreach ($pageDataLocations as $pageDataClassName => $properties) {
            $pageDataResources = $this->findPageDataResourcesByPropertiesAndComponent($pageDataClassName, $properties, $component);
            if (!$pageDataResources) {
                continue;
            }
            $componentInDataCount = 0;
            foreach ($pageDataResources as $pageDataResource) {
                foreach ($properties as $property) {
                    if ($this->propertyAccessor->getValue($pageDataResource, $property) === $component) {
                        ++$componentInDataCount;
                    }
                }
            }
            $pageDataCount += $componentInDataCount;
        }

        return $pageDataCount;
    }

    private function findPageDataResourcesByPropertiesAndComponent(string $pageDataClassName, ArrayCollection $properties, ComponentInterface $component): ?array
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

        return $qb->getQuery()->getResult();
    }

    private function getPageDataLocations(string $resourceShortName): array
    {
        $pageDataMetadatas = $this->pageDataMetadataProvider->createAll();
        $pageDataLocations = [];
        foreach ($pageDataMetadatas as $pageDataMetadata) {
            $resourceProperties = $pageDataMetadata->findPropertiesByComponentClass($resourceShortName);
            if ($resourceProperties->count() > 0) {
                $pageDataLocations[$pageDataMetadata->getResourceClass()] = $resourceProperties->map(static function (PageDataPropertyMetadata $metadata) {
                    return $metadata->getProperty();
                });
            }
        }

        return $pageDataLocations;
    }
}
