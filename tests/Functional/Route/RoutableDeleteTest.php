<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Route;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class RoutableDeleteTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use SiteTrait;

    public function test_deleting_a_routed_page_removes_its_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $route = self::createRouteWithPage('/doomed');
        $routeIri = self::iriOf($route);
        $pageIri = self::iriOf(self::routeAt('/doomed')->getPage());

        self::requestJsonLd($client, 'DELETE', $pageIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($pageIri);
        self::assertResourceNotExists($routeIri);
    }

    public function test_deleting_routed_page_data_removes_its_route(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createPageDataWithRoute('/doomed-data');
        \assert(null !== $created['route']);
        $routeIri = self::iriOf($created['route']);
        $pageDataIri = self::iriOf($created['pageData']);

        self::requestJsonLd($client, 'DELETE', $pageDataIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($pageDataIri);
        self::assertResourceNotExists($routeIri);
    }

    public function test_deleting_a_page_whose_route_also_redirects_keeps_the_route_as_a_redirect(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $target = self::newRoute('/target');
        $page = self::newPage('redirecting-page');
        $route = self::newRoute('/redirecting')->setPage($page)->setRedirect($target);
        self::persistAndFlush($target, $page, $route);
        $pageIri = self::iriOf($page);

        self::requestJsonLd($client, 'DELETE', $pageIri);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($pageIri);
        self::assertRouteRedirectsTo('/redirecting', '/target');
        self::assertNull(self::routeAt('/redirecting')->getPage());
    }
}
