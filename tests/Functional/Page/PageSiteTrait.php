<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

trait PageSiteTrait
{
    use SiteTrait;

    /**
     * @return array{parentPage: Page, parentRoute: Route, page: Page, route: Route}
     */
    protected static function createNestedPages(string $childPath, string $parentPath): array
    {
        $parentPage = static::newPage('parent page', true);
        $parentRoute = static::newRoute($parentPath)->setPage($parentPage);
        $page = static::newPage('child page', true);
        $page->setParentPage($parentPage);
        $route = static::newRoute($childPath)->setPage($page);
        static::persistAndFlush($parentPage, $parentRoute, $page, $route);

        return ['parentPage' => $parentPage, 'parentRoute' => $parentRoute, 'page' => $page, 'route' => $route];
    }

    protected static function createDummyComponent(): DummyComponent
    {
        $component = new DummyComponent();
        static::persistAndFlush($component);

        return $component;
    }

    /**
     * @return array{group: ComponentGroup, components: list<DummyComponent>, positions: list<ComponentPosition>}
     */
    protected static function giveComponentAGroupHoldingAComponent(AbstractComponent $component, string $reference): array
    {
        $created = static::createComponentGroupWithComponents(1, $reference);
        $manager = static::getEntityManager();
        $manager->find($component::class, $component->getId())->addComponentGroup($manager->find(ComponentGroup::class, $created['group']->getId()));
        $manager->flush();
        static::rebootKernel();

        return $created;
    }

    /**
     * @return array{group: ComponentGroup, components: list<DummyComponent>, positions: list<ComponentPosition>}
     */
    protected static function givePageAGroupHoldingAComponent(Page $page, string $reference): array
    {
        $created = static::createComponentGroupWithComponents(1, $reference);
        $manager = static::getEntityManager();
        $manager->find(Page::class, $page->getId())->addComponentGroup($manager->find(ComponentGroup::class, $created['group']->getId()));
        $manager->flush();
        static::rebootKernel();

        return $created;
    }

    /**
     * @return array{group: ComponentGroup, position: ComponentPosition}
     */
    protected static function giveComponentAGroupHoldingItself(AbstractComponent $component, string $reference): array
    {
        $manager = static::getEntityManager();
        $component = $manager->find($component::class, $component->getId());
        $group = static::newComponentGroup($reference);
        $position = static::newComponentPosition($group, $component, 0);
        $component->addComponentGroup($group);
        static::persistAndFlush($group, $position);

        return ['group' => $group, 'position' => $position];
    }

    /**
     * @param list<Page> $pages
     *
     * @return array{layout: Layout, group: ComponentGroup, component: DummyComponent, position: ComponentPosition}
     */
    protected static function usePagesWithLayoutHoldingAComponent(array $pages): array
    {
        $layout = static::newLayout('shared-layout');
        static::persistAndFlush($layout);
        $created = static::createComponentGroupWithComponents(1, 'layout_top');
        $manager = static::getEntityManager();
        $managedLayout = $manager->find(Layout::class, $layout->getId());
        $managedLayout->addComponentGroup($manager->find(ComponentGroup::class, $created['group']->getId()));
        foreach ($pages as $page) {
            $manager->find(Page::class, $page->getId())->layout = $managedLayout;
        }
        $manager->flush();
        static::rebootKernel();

        return ['layout' => $layout, 'group' => $created['group'], 'component' => $created['components'][0], 'position' => $created['positions'][0]];
    }
}
