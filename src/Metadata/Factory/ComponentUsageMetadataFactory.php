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

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
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
        ManagerRegistry $registry
    ) {
        $this->componentPositionRepository = $componentPositionRepository;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->pageDataProvider = $pageDataProvider;
        $this->publishableStatusChecker = $publishableStatusChecker;
        $this->initRegistry($registry);
    }

    public function create(ComponentInterface $component): ComponentUsageMetadata
    {
        $annotationReader = $this->publishableStatusChecker->getAttributeReader();
        if ($annotationReader->isConfigured($component) && !$this->publishableStatusChecker->isActivePublishedAt($component)) {
            // get the published component to run checks against
            $configuration = $annotationReader->getConfiguration($component);
            $classMetadata = $this->getClassMetadata($component);

            $publishedResourceAssociation = $classMetadata->getFieldValue($component, $configuration->associationName);
            if ($publishedResourceAssociation) {
                $component = $publishedResourceAssociation;
            }
        }
        $componentPositions = $this->componentPositionRepository->findByComponent($component);
        $componentPositionCount = \count($componentPositions);

        $pageDataCount = $this->getPageDataTotal($component);

        return new ComponentUsageMetadata($componentPositionCount, $pageDataCount);
    }

    private function getPageDataTotal(ComponentInterface $component): int
    {
        /** @var \Generator $pageDataLocations */
        $pageDataLocations = $this->pageDataProvider->findPageDataComponentMetadata($component);
        $pageDataCount = 0;

        foreach ($pageDataLocations as $pageDataComponentMetadata) {
            $componentInDataCount = 0;
            foreach ($pageDataComponentMetadata->getPageDataResources() as $pageDataResource) {
                foreach ($pageDataComponentMetadata->getProperties() as $property) {
                    if ($this->propertyAccessor->getValue($pageDataResource, $property) === $component) {
                        ++$componentInDataCount;
                    }
                }
            }
            $pageDataCount += $componentInDataCount;
        }

        return $pageDataCount;
    }
}
