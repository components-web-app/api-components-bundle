<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Site;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @internal
 */
final readonly class SiteReader
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function read(): SiteSnapshot
    {
        /** @var list<Layout> $layouts */
        $layouts = $this->registry->getRepository(Layout::class)->findAll();
        /** @var list<Page> $pages */
        $pages = $this->registry->getRepository(Page::class)->findAll();
        /** @var list<AbstractPageData> $pageData */
        $pageData = $this->registry->getRepository(AbstractPageData::class)->findAll();
        /** @var list<Route> $routes */
        $routes = $this->registry->getRepository(Route::class)->findAll();

        usort($layouts, static fn (Layout $a, Layout $b): int => strcmp((string) $a->reference, (string) $b->reference));

        $childPages = [];
        $childPageData = [];
        $topPages = [];
        $topPageData = [];
        foreach ($pages as $page) {
            $parent = $page->getParentPageData() ?? $page->getParentPage();
            null === $parent ? $topPages[] = $page : $childPages[spl_object_id($parent)][] = $page;
        }
        foreach ($pageData as $item) {
            $parent = $item->getParentPageData() ?? $item->getParentPage();
            null === $parent ? $topPageData[] = $item : $childPageData[spl_object_id($parent)][] = $item;
        }

        $children = [];
        foreach (array_unique([...array_keys($childPages), ...array_keys($childPageData)]) as $parentId) {
            $children[$parentId] = [...$this->sortPages($childPages[$parentId] ?? []), ...$this->sortPageData($childPageData[$parentId] ?? [])];
        }

        $topPages = $this->sortPages($topPages);
        $topPageData = $this->sortPageData($topPageData);

        $reached = [];
        $siteRoutes = [];
        $queue = [...$topPages, ...$topPageData];
        while (null !== ($entity = array_shift($queue))) {
            if (isset($reached[spl_object_id($entity)])) {
                continue;
            }
            $reached[spl_object_id($entity)] = true;
            if (null !== ($route = $entity->getRoute())) {
                $siteRoutes[] = $route;
            }
            array_push($queue, ...$children[spl_object_id($entity)] ?? []);
        }

        $unreached = array_values(array_filter([...$pages, ...$pageData], static fn (AbstractPage $page): bool => !isset($reached[spl_object_id($page)])));

        [$redirects, $unresolved] = $this->orderRedirects($routes, $siteRoutes);

        return new SiteSnapshot($layouts, $topPages, $topPageData, $children, $siteRoutes, $redirects, $unresolved, $unreached);
    }

    /**
     * @param list<Route> $routes
     * @param list<Route> $siteRoutes
     *
     * @return array{list<Route>, list<Route>}
     */
    private function orderRedirects(array $routes, array $siteRoutes): array
    {
        $available = [];
        foreach ($siteRoutes as $route) {
            $available[spl_object_id($route)] = true;
        }

        $pending = [];
        foreach ($routes as $route) {
            if (null !== $route->getRedirect() && !isset($available[spl_object_id($route)])) {
                $pending[spl_object_id($route)] = $route;
            }
        }

        $ordered = [];
        while (true) {
            $ready = array_filter($pending, static fn (Route $route): bool => isset($available[spl_object_id($route->getRedirect())]));
            if ([] === $ready) {
                break;
            }
            $ready = $this->sortByPath(array_values($ready));
            foreach ($ready as $route) {
                $ordered[] = $route;
                $available[spl_object_id($route)] = true;
                unset($pending[spl_object_id($route)]);
            }
        }

        return [$ordered, $this->sortByPath(array_values($pending))];
    }

    /**
     * @param list<Page> $pages
     *
     * @return list<Page>
     */
    private function sortPages(array $pages): array
    {
        usort($pages, static fn (Page $a, Page $b): int => [self::pageRank($a), self::routePath($a), (string) $a->reference] <=> [self::pageRank($b), self::routePath($b), (string) $b->reference]);

        return $pages;
    }

    /**
     * @param list<AbstractPageData> $pageData
     *
     * @return list<AbstractPageData>
     */
    private function sortPageData(array $pageData): array
    {
        usort($pageData, static fn (AbstractPageData $a, AbstractPageData $b): int => [null === $a->getRoute(), self::routePath($a), (string) $a->getTitle()] <=> [null === $b->getRoute(), self::routePath($b), (string) $b->getTitle()]);

        return $pageData;
    }

    /**
     * @param list<Route> $routes
     *
     * @return list<Route>
     */
    private function sortByPath(array $routes): array
    {
        usort($routes, static fn (Route $a, Route $b): int => strcmp($a->getPath(), $b->getPath()));

        return $routes;
    }

    private static function pageRank(Page $page): int
    {
        return match (true) {
            null !== $page->getRoute() => 0,
            !$page->isTemplate => 1,
            default => 2,
        };
    }

    private static function routePath(AbstractPage $page): string
    {
        return $page->getRoute()?->getPath() ?? '';
    }
}
