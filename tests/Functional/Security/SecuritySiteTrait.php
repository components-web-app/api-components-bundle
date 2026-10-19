<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Security;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

trait SecuritySiteTrait
{
    use SiteTrait;

    /**
     * @return array{page: string, pageData: string, component: string}
     */
    protected static function createComponentInPageDataRoute(?string $path, ?AbstractPageData $pageData = null, string $pageReference = 'page_data_page', string $groupReference = 'page_data_cc'): array
    {
        $page = static::newPage($pageReference);
        $pageData ??= new PageData();
        $pageData->page = $page;
        $entities = [$page, $pageData];
        if (null !== $path) {
            $entities[] = static::newRoute($path)->setPageData($pageData);
        }
        $group = static::newComponentGroup($groupReference);
        $page->addComponentGroup($group);
        $component = new DummyComponent();
        array_push($entities, $group, $component, static::newComponentPosition($group, $component, 0));
        static::persistAndFlush(...$entities);

        return ['page' => static::iriOf($page), 'pageData' => static::iriOf($pageData), 'component' => static::iriOf($component)];
    }

    /**
     * @return array{page: string, pageData: string, component: string}
     */
    protected static function createComponentInRestrictedPageDataRoute(?string $path): array
    {
        return static::createComponentInPageDataRoute($path, new RestrictedPageData(), 'restricted_page_data_page', 'restricted_page_data_cc');
    }

    protected static function placeComponentInRoute(string $componentIri, string $path): void
    {
        $component = static::findByIri($componentIri);
        self::assertInstanceOf(AbstractComponent::class, $component);
        $page = static::newPage('page_1');
        $group = static::newComponentGroup('test');
        $page->addComponentGroup($group);
        static::persistAndFlush($page, static::newRoute($path)->setPage($page), $group, static::newComponentPosition($group, $component, 0));
    }

    protected static function placeComponentInPageDataRoute(string $componentIri, string $path): void
    {
        $component = static::findByIri($componentIri);
        self::assertInstanceOf(AbstractComponent::class, $component);
        $page = static::newPage('page_data_page');
        $pageData = new PageData();
        $pageData->page = $page;
        $group = static::newComponentGroup('page_data_cc');
        $page->addComponentGroup($group);
        static::persistAndFlush($page, $pageData, static::newRoute($path)->setPageData($pageData), $group, static::newComponentPosition($group, $component, 0));
    }

    /**
     * @return array{parentPageData: string, parentTemplate: string, parentComponent: string, parentManifest: string, childPage: string}
     */
    protected static function createRoutelessParentPageDataWithRoutedChild(?string $path, string $groupReference = 'routeless parent group', string $childReference = 'routed child page'): array
    {
        $group = self::newReachabilityGroup($groupReference);
        $template = static::newPage('routeless parent template', true);
        $template->addComponentGroup($group['group']);
        $parentPageData = new PageData();
        $parentPageData->page = $template;
        $child = static::newPage($childReference);
        $child->setParentPageData($parentPageData);
        $entities = [$group['component'], $group['group'], $group['position'], $template, $parentPageData, $child];
        if (null !== $path) {
            $entities[] = static::newRoute($path)->setPage($child);
        }
        static::persistAndFlush(...$entities);

        return [
            'parentPageData' => static::iriOf($parentPageData),
            'parentTemplate' => static::iriOf($template),
            'parentComponent' => static::iriOf($group['component']),
            'parentManifest' => '/_/resource_manifest/' . $parentPageData->getId(),
            'childPage' => static::iriOf($child),
        ];
    }

    /**
     * @return array{parentPageData: string, parentTemplate: string, parentComponent: string, parentManifest: string, childPage: string}
     */
    protected static function createRoutelessParentPageDataWithUnroutedChild(): array
    {
        return static::createRoutelessParentPageDataWithRoutedChild(null, 'unreachable parent group', 'unrouted child page');
    }

    /**
     * @return array{parentPage: string, parentComponent: string}
     */
    protected static function createRoutelessParentPageWithRoutedChild(string $path): array
    {
        $group = self::newReachabilityGroup('routeless parent page group');
        $parent = static::newPage('routeless parent page');
        $parent->addComponentGroup($group['group']);
        $child = static::newPage('routed child page');
        $child->setParentPage($parent);
        static::persistAndFlush($group['component'], $group['group'], $group['position'], $parent, $child, static::newRoute($path)->setPage($child));

        return ['parentPage' => static::iriOf($parent), 'parentComponent' => static::iriOf($group['component'])];
    }

    /**
     * @return array{chainRoot: string, parentComponent: string}
     */
    protected static function createChainOfRoutelessPages(int $depth, string $path): array
    {
        $group = self::newReachabilityGroup('chain group');
        $entities = [$group['component'], $group['group'], $group['position']];
        $root = null;
        $parent = null;
        for ($i = 0; $i < $depth; ++$i) {
            $page = static::newPage('chain page ' . $i);
            if (0 === $i) {
                $page->addComponentGroup($group['group']);
            }
            if (null !== $parent) {
                $page->setParentPage($parent);
            }
            $entities[] = $page;
            $root ??= $page;
            $parent = $page;
        }
        $leaf = static::newPage('chain leaf');
        $leaf->setParentPage($parent);
        array_push($entities, $leaf, static::newRoute($path)->setPage($leaf));
        static::persistAndFlush(...$entities);

        return ['chainRoot' => static::iriOf($root), 'parentComponent' => static::iriOf($group['component'])];
    }

    protected static function createComponentOnRoutedPageAlsoOnRoutelessPage(string $path): string
    {
        $group = self::newReachabilityGroup('routed page group');
        $routedPage = static::newPage('shared component routed page');
        $routedPage->addComponentGroup($group['group']);
        $routelessGroup = static::newComponentGroup('routeless page group');
        $routelessPage = static::newPage('shared component routeless page');
        $routelessPage->addComponentGroup($routelessGroup);
        static::persistAndFlush(
            $group['component'],
            $group['group'],
            $group['position'],
            $routedPage,
            static::newRoute($path)->setPage($routedPage),
            $routelessGroup,
            static::newComponentPosition($routelessGroup, $group['component'], 0),
            $routelessPage,
        );

        return static::iriOf($group['component']);
    }

    protected static function createRoutelessPageWithNoRoutedDescendant(): string
    {
        $group = self::newReachabilityGroup('orphan group');
        $page = static::newPage('orphan routeless page');
        $page->addComponentGroup($group['group']);
        static::persistAndFlush($group['component'], $group['group'], $group['position'], $page);

        return static::iriOf($group['component']);
    }

    protected static function createRoutelessPageDataCycle(): string
    {
        $template = static::newPage('cycle template one', true);
        $first = new PageData();
        $first->page = $template;
        $second = new PageData();
        $second->page = $template;
        static::persistAndFlush($template, $first, $second);

        $manager = static::getEntityManager();
        $first = $manager->find(PageData::class, $first->getId());
        $second = $manager->find(PageData::class, $second->getId());
        $first->setParentPageData($second);
        $second->setParentPageData($first);
        static::persistAndFlush($first, $second);

        return static::iriOf($first);
    }

    /**
     * @return array{group: ComponentGroup, component: DummyComponent, position: ComponentPosition}
     */
    private static function newReachabilityGroup(string $reference): array
    {
        $component = new DummyComponent();
        $group = static::newComponentGroup($reference);

        return ['group' => $group, 'component' => $component, 'position' => static::newComponentPosition($group, $component, 0)];
    }
}
