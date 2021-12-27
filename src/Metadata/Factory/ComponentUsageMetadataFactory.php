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

use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentPositionRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageMetadataFactory
{
    private ComponentPositionRepository $componentPositionRepository;
    private PropertyAccessor $propertyAccessor;
    private PageDataProvider $pageDataProvider;

    public function __construct(
        ComponentPositionRepository $componentPositionRepository,
        PageDataProvider $pageDataProvider
    ) {
        $this->componentPositionRepository = $componentPositionRepository;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->pageDataProvider = $pageDataProvider;
    }

    public function create(ComponentInterface $component): ComponentUsageMetadata
    {
        $componentPositions = $this->componentPositionRepository->findByComponent($component);
        $componentPositionCount = \count($componentPositions);

        $pageDataCount = $this->getPageDataTotal($component);

        return new ComponentUsageMetadata($componentPositionCount, $pageDataCount);
    }

    private function getPageDataTotal(ComponentInterface $component): int
    {
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
