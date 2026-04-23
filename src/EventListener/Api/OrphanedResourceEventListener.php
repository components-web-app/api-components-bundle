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

namespace Silverback\ApiComponentsBundle\EventListener\Api;

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
readonly class OrphanedResourceEventListener {
    public function __construct(private PageDataMetadataFactoryInterface $pageDataMetadataFactory, private ComponentUsageMetadataFactory $usageMetadataFactory, private ManagerRegistry $registry)
    {
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $data = $request->attributes->get('data');
        $resourceClass = $request->attributes->get('_api_resource_class');
        // only listen for deleted
        if (!$request->isMethod(Request::METHOD_DELETE)) {
            return;
        }

        if ($data instanceof ComponentPosition) {
            $this->removeOrphanedComponentPosition($data);
            return;
        }

        if ($data instanceof Page || $data instanceof AbstractComponent || $data instanceof Layout) {
            foreach ($data->getComponentGroups() as $componentGroup) {
                $this->removeOrphanedComponentGroup($componentGroup, $data);
            }
            return;
        }

        if ($data instanceof ComponentGroup) {
            $this->removeOrphanedComponentGroup($data);
            return;
        }

        if ($data instanceof PageDataInterface) {
            $propertyAccessor = PropertyAccess::createPropertyAccessor();
            $pageDataMetadata = $this->pageDataMetadataFactory->create($resourceClass);
            foreach ($pageDataMetadata->getProperties() as $property) {
                $component = $propertyAccessor->getValue($data, $property->getProperty());
                if ($component instanceof ComponentInterface) {
                    $this->removeOrphanedComponent($component);
                }
            }
        }

        if ($data instanceof RoutableInterface) {
            $route = $data->getRoute();
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

    private function removeOrphanedComponentGroup(ComponentGroup $componentGroup, AbstractComponent|Page|Layout|null $deletedLocation = null): void {
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

    private function removeOrphanedComponentPosition(ComponentPosition $componentPosition): void {
        if ($componentPosition->component) {
            $this->removeOrphanedComponent($componentPosition->component);
        }
    }

    private function removeOrphanedComponent(ComponentInterface $component): void
    {
        $metadata = $this->usageMetadataFactory->create($component);
        if (1 === $metadata->getTotal()) {
            $resourceClass = get_class($component);
            $manager = $this->registry->getManagerForClass($resourceClass);
            $manager?->remove($component);
        }
    }
}
