<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
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
        private PublishableAttributeReader $publishableAttributeReader,
        private PageDataProvider $pageDataProvider,
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

    public function handleRemovedOrphanedComponent(AbstractComponent $component): void
    {
        $this->handleRemovedRootResource($component);
        $this->removeUnusedDraft($component);
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

    private function isComponentGroupInOtherLocations(ComponentGroup $componentGroup, AbstractComponent|Page|Layout|null $deletedLocation = null): bool
    {
        if (!$deletedLocation) {
            return false;
        }
        foreach ([$componentGroup->pages, $componentGroup->layouts, $componentGroup->components] as $owners) {
            foreach ($owners as $owner) {
                if ($owner !== $deletedLocation && !$this->isScheduledForDelete($owner)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function removeOrphanedComponentGroup(ComponentGroup $componentGroup, AbstractComponent|Page|Layout|null $deletedLocation = null): void
    {
        if ($this->isScheduledForDelete($componentGroup) || $this->isComponentGroupInOtherLocations($componentGroup, $deletedLocation)) {
            return;
        }
        $this->registry->getManagerForClass(ComponentGroup::class)?->remove($componentGroup);

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

    private function removeOrphanedComponent(ComponentInterface $component): void
    {
        if ($this->isScheduledForDelete($component) || 1 !== $this->usageMetadataFactory->create($component)->getTotal()) {
            return;
        }
        $this->registry->getManagerForClass($component::class)?->remove($component);
        if ($component instanceof AbstractComponent) {
            $this->handleRemovedOrphanedComponent($component);
        }
    }

    private function removeUnusedDraft(AbstractComponent $component): void
    {
        $entityManager = $this->registry->getManagerForClass($component::class);
        if (!$entityManager instanceof EntityManagerInterface || !$this->publishableAttributeReader->isConfigured($component)) {
            return;
        }
        $configuration = $this->publishableAttributeReader->getConfiguration($component);
        $draft = $entityManager->getClassMetadata($component::class)->getFieldValue($component, $configuration->reverseAssociationName);
        if (!$draft instanceof AbstractComponent || $this->isScheduledForDelete($draft) || $this->hasSurvivingPosition($component) || $this->isInUse($draft)) {
            return;
        }
        $entityManager->remove($draft);
        $this->handleRemovedOrphanedComponent($draft);
    }

    private function isInUse(AbstractComponent $component): bool
    {
        if ($this->hasSurvivingPosition($component)) {
            return true;
        }
        foreach ($this->pageDataProvider->findPageDataComponentMetadata($component) as $pageDataComponentMetadata) {
            if (\count($pageDataComponentMetadata->getPageDataResources())) {
                return true;
            }
        }

        return false;
    }

    private function hasSurvivingPosition(AbstractComponent $component): bool
    {
        foreach ($component->getComponentPositions() as $position) {
            if (!$this->isScheduledForDelete($position)) {
                return true;
            }
        }

        return false;
    }

    private function isScheduledForDelete(object $resource): bool
    {
        $entityManager = $this->registry->getManagerForClass($resource::class);

        return $entityManager instanceof EntityManagerInterface && $entityManager->getUnitOfWork()->isScheduledForDelete($resource);
    }
}
