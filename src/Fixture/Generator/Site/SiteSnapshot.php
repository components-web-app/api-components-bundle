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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @internal
 */
final readonly class SiteSnapshot
{
    /**
     * @param list<Layout>                   $layouts
     * @param list<Page>                     $pages
     * @param list<AbstractPageData>         $pageData
     * @param array<int, list<AbstractPage>> $children
     * @param list<Route>                    $routes
     * @param list<Route>                    $redirects
     * @param list<Route>                    $unresolvedRedirects
     * @param list<AbstractPage>             $unreached
     */
    public function __construct(
        public array $layouts = [],
        public array $pages = [],
        public array $pageData = [],
        private array $children = [],
        public array $routes = [],
        public array $redirects = [],
        public array $unresolvedRedirects = [],
        public array $unreached = [],
    ) {
    }

    /**
     * @return list<AbstractPage>
     */
    public function childrenOf(AbstractPage $parent): array
    {
        return $this->children[spl_object_id($parent)] ?? [];
    }

    public function contains(Route $route): bool
    {
        return \in_array($route, $this->routes, true) || \in_array($route, $this->redirects, true);
    }
}
