<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Entity\Core;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

#[\PHPUnit\Framework\Attributes\CoversClass(Route::class)]
class RouteTest extends TestCase
{
    public function test_set_path_prepends_slash_when_missing(): void
    {
        $route = new Route();
        $route->setPath('my-page');

        self::assertSame('/my-page', $route->getPath());
    }

    public function test_set_path_does_not_prepend_slash_when_already_present(): void
    {
        $route = new Route();
        $route->setPath('/already-slashed');

        self::assertSame('/already-slashed', $route->getPath());
    }

    public function test_set_path_empty_string_is_stored_as_is(): void
    {
        // Empty string '' !== '' is false, so the condition is skipped and path stays ''
        $route = new Route();
        $route->setPath('');

        self::assertSame('', $route->getPath());
    }

    public function test_set_page_data_sets_reverse_route_on_page_data(): void
    {
        $pageData = new class extends AbstractPageData {
            public Page $page;
        };

        $route = new Route();
        $route->setPageData($pageData);

        // The bidirectional link must be established: pageData.route === route
        self::assertSame($route, $pageData->getRoute());
        self::assertSame($pageData, $route->getPageData());
    }

    public function test_set_page_data_null_does_not_set_reverse_link(): void
    {
        $route = new Route();
        $route->setPageData(null);

        self::assertNull($route->getPageData());
    }

    public function test_set_page_sets_reverse_route_on_page(): void
    {
        $page = new Page();
        $route = new Route();
        $route->setPage($page);

        self::assertSame($route, $page->getRoute());
        self::assertSame($page, $route->getPage());
    }
}
