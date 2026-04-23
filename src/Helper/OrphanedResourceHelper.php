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

namespace Silverback\ApiComponentsBundle\Helper;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

final readonly class OrphanedResourceHelper
{
    public function __construct(
        private PageDataMetadataFactoryInterface $pageDataMetadataFactory,
        private ComponentUsageMetadataFactory $usageMetadataFactory,
        private ManagerRegistry $registry,
    ) {
    }

    public function handleRemovedComponentPosition(ComponentPosition $componentPosition): void
    {
        $this->removeOrphanedComponentPosition($componentPosition);
    }

    public function handleRemovedRootResource(AbstractComponent|Page|Layout $resource): void
    {
        foreach ($resource->getComponentGroups() as $componentGroup) {
            $this->removeOrphanedComponentGroup($componentGroup, $resource);
        }
    }

    public function handleRemovedComponentGroup(ComponentGroup $componentGroup): void
    {
        $this->removeOrphanedComponentGroup($componentGroup);
    }

    public function handleRemovedPageData(PageDataInterface $resource, ?string $resourceClass): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $pageDataMetadata = $this->pageDataMetadataFactory->create($resourceClass ?: $resource::class);
        foreach ($pageDataMetadata->getProperties() as $property) {
            $component = $propertyAccessor->getValue($resource, $property->getProperty());
            if ($component instanceof ComponentInterface) {
                $this->removeOrphanedComponent($component);
            }
        }
    }

    public function handleRemovedRoutable(RoutableInterface $resource): void
    {
        $route = $resource->getRoute();
        if ($route) {
            $routeAssociations = 0;
            $route->getPage() && $routeAssociations++;
            $route->getPageData() && $routeAssociations++;
            $route->getRedirect() && $routeAssociations++;
            if ($routeAssociations <= 1) {
                $manager = $this->registry->getManagerForClass(Route::class);
                $manager?->remove($route);
            }
        }
    }

    public function checkAndRemoveOrphanedComponentGroup(ComponentGroup $componentGroup): bool
    {
        if ($componentGroup->pages->count() || $componentGroup->layouts->count() || $componentGroup->components->count()) {
            return false;
        }
        $groupManager = $this->registry->getManagerForClass(ComponentGroup::class);

        $positionManager = $this->registry->getManagerForClass(ComponentPosition::class);
        foreach ($componentGroup->componentPositions as $componentPosition) {
            $positionManager?->remove($componentPosition);
            $this->removeOrphanedComponentPosition($componentPosition);
        }

        $groupManager?->remove($componentGroup);
        $groupManager?->flush();
        $positionManager?->flush();

        return true;
    }

    public function checkAndRemoveOrphanedComponent(AbstractComponent $component): bool
    {
        return $this->removeOrphanedComponent($component, 0, true);
    }

    private function isComponentGroupInOtherLocations(ComponentGroup $componentGroup, AbstractComponent|Page|Layout|null $deletedLocation = null): bool
    {
        if (!$deletedLocation) {
            return false;
        }
        foreach ($componentGroup->pages as $page) {
            if ($page !== $deletedLocation) {
                return true;
            }
        }
        foreach ($componentGroup->layouts as $layout) {
            if ($layout !== $deletedLocation) {
                return true;
            }
        }
        foreach ($componentGroup->components as $component) {
            if ($component !== $deletedLocation) {
                return true;
            }
        }

        return false;
    }

    private function removeOrphanedComponentGroup(ComponentGroup $componentGroup, AbstractComponent|Page|Layout|null $deletedLocation = null): void
    {
        $groupExistsElsewhere = $this->isComponentGroupInOtherLocations($componentGroup, $deletedLocation);
        if ($groupExistsElsewhere) {
            return;
        }
        $groupManager = $this->registry->getManagerForClass(ComponentGroup::class);
        $groupManager?->remove($componentGroup);

        // delete it AND check for orphaned component positions to delete because even if they delete
        // automatically, we may have orphaned components too
        $positionManager = $this->registry->getManagerForClass(ComponentPosition::class);
        foreach ($componentGroup->componentPositions as $componentPosition) {
            $positionManager?->remove($componentPosition);
            $this->removeOrphanedComponentPosition($componentPosition);
        }
    }

    private function removeOrphanedComponentPosition(ComponentPosition $componentPosition): void
    {
        if ($componentPosition->component) {
            $this->removeOrphanedComponent($componentPosition->component);
        }
    }

    private function removeOrphanedComponent(ComponentInterface $component, int $countCheck = 1, bool $doFlush = false): bool
    {
        $metadata = $this->usageMetadataFactory->create($component);
        if ($countCheck === $metadata->getTotal()) {
            $resourceClass = $component::class;
            $manager = $this->registry->getManagerForClass($resourceClass);
            $manager?->remove($component);
            if ($doFlush) {
                $manager?->flush();
            }

            return true;
        }

        return false;
    }
}
