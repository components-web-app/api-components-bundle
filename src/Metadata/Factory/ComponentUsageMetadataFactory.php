<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Metadata\Factory;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Helper\ComponentUsage\ComponentUsageLocator;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageMetadataFactory
{
    use ClassMetadataTrait;

    private ComponentPositionRepository $componentPositionRepository;
    private PropertyAccessor $propertyAccessor;
    private PageDataProvider $pageDataProvider;
    private PublishableStatusChecker $publishableStatusChecker;

    public function __construct(
        ComponentPositionRepository $componentPositionRepository,
        PageDataProvider $pageDataProvider,
        PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry,
        private readonly ComponentUsageLocator $locator,
    ) {
        $this->componentPositionRepository = $componentPositionRepository;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->pageDataProvider = $pageDataProvider;
        $this->publishableStatusChecker = $publishableStatusChecker;
        $this->initRegistry($registry);
    }

    public function create(ComponentInterface $component, bool $withLocations = false): ComponentUsageMetadata
    {
        $annotationReader = $this->publishableStatusChecker->getAttributeReader();
        if ($annotationReader->isConfigured($component) && !$this->publishableStatusChecker->isActivePublishedAt($component)) {
            $configuration = $annotationReader->getConfiguration($component);
            $classMetadata = $this->getClassMetadata($component);

            $publishedResourceAssociation = $classMetadata->getFieldValue($component, $configuration->associationName);
            if ($publishedResourceAssociation) {
                $component = $publishedResourceAssociation;
            }
        }
        $componentPositions = array_values($this->componentPositionRepository->findByComponent($component));
        $pageDataUses = $this->findPageDataUses($component);

        return new ComponentUsageMetadata(
            \count($componentPositions),
            \count($pageDataUses),
            $withLocations ? $this->locator->locate($componentPositions, $pageDataUses) : null,
        );
    }

    /**
     * @return list<array{pageData: AbstractPageData, property: string}>
     */
    private function findPageDataUses(ComponentInterface $component): array
    {
        $uses = [];
        foreach ($this->pageDataProvider->findPageDataComponentMetadata($component) as $pageDataComponentMetadata) {
            foreach ($pageDataComponentMetadata->getPageDataResources() as $pageDataResource) {
                foreach ($pageDataComponentMetadata->getProperties() as $property) {
                    if ($this->propertyAccessor->getValue($pageDataResource, $property) === $component) {
                        $uses[] = ['pageData' => $pageDataResource, 'property' => $property];
                    }
                }
            }
        }

        return $uses;
    }
}
