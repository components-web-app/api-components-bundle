<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class SiteTraitTest extends FunctionalTestCase
{
    use SiteTrait;

    private const array JSON_LD = ['accept' => 'application/ld+json'];

    public function test_a_page_and_a_layout_are_created_with_their_references(): void
    {
        $client = static::createClient();

        $page = self::createPage('my-page', true);
        $layout = self::createLayout('my-layout', 'CwaLayoutPrimary');
        $parameter = self::createSiteConfigParameter('site_name', 'CWA');

        $stored = self::findByIri(self::iriOf($page));
        self::assertInstanceOf(Page::class, $stored);
        self::assertSame('my-page', $stored->reference);
        self::assertTrue($stored->isTemplate);
        $storedLayout = self::findByIri(self::iriOf($layout));
        self::assertInstanceOf(Layout::class, $storedLayout);
        self::assertSame('my-layout', $storedLayout->reference);
        self::assertSame('CwaLayoutPrimary', $storedLayout->uiComponent);
        self::assertSame('CWA', self::findByIri(self::iriOf($parameter))?->getValue());
        $client->request('GET', self::iriOf($layout), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_route_with_a_page_is_public_and_can_be_scheduled(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/about');

        $client->request('GET', '/_/routes//about', ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($route->getPage()), $client->getResponse()?->toArray()['page']);

        self::setRouteLiveAt('/about', new \DateTimeImmutable('+1 day'));
        $client->request('GET', '/_/routes//about', ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('route-page', self::routeAt('/about')->getPage()?->reference);
        self::assertRouteDoesNotRedirect('/about');
    }

    public function test_a_redirecting_route_is_asserted_by_its_target_path(): void
    {
        static::createClient();
        $target = self::createRouteWithPage('/new');
        $manager = self::getEntityManager();
        $redirect = self::newRoute('/old')->setRedirect($manager->find(Route::class, $target->getId()));
        self::persistAndFlush($redirect);

        self::assertRouteRedirectsTo('/old', '/new');
    }

    public function test_asserting_a_route_that_does_not_exist_fails(): void
    {
        static::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No route has the path "/missing".');

        self::routeAt('/missing');
    }

    public function test_a_component_group_holds_its_components_in_order(): void
    {
        $client = static::createClient();

        $created = self::createComponentGroupWithComponents(3);

        $client->request('GET', self::iriOf($created['group']), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(array_map(self::iriOf(...), $created['positions']), $client->getResponse()?->toArray()['componentPositions']);
        self::assertSame([0, 1, 2], array_map(static fn (ComponentPosition $position): ?int => $position->sortValue, $created['positions']));
        self::assertSame('collection', $created['group']->reference);
        self::assertCount(3, $created['components']);
    }

    public function test_a_routed_page_with_a_component_is_reachable_through_its_route(): void
    {
        $client = static::createClient();

        $created = self::createRoutedPageWithComponent('/with-component');

        $client->request('GET', self::iriOf($created['component']), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('manifest-tag-page', $created['page']->reference);
        self::assertSame($created['layout'], $created['page']->layout);
        self::assertSame('/with-component', $created['route']->getPath());
        self::assertSame($created['group'], $created['position']->componentGroup);
    }

    public function test_a_component_in_a_route_is_public(): void
    {
        $client = static::createClient();

        $created = self::createComponentInRoute('/component-route');

        $client->request('GET', self::iriOf($created['position']), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($created['component']), $client->getResponse()?->toArray()['component']);
        self::assertSame('page', $created['page']->reference);
        self::assertSame($created['page'], $created['route']->getPage());
        self::assertSame($created['group'], $created['position']->componentGroup);
    }

    public function test_page_data_is_routed_only_when_a_path_is_given(): void
    {
        $client = static::createClient();

        $routed = self::createPageDataWithRoute('/page-data');
        $client->request('GET', '/_/resource_manifest//page-data', ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($routed['pageData'], $routed['route']?->getPageData());
        self::assertSame('component', $routed['position']->pageDataProperty);
        self::assertSame($routed['component'], $routed['pageData']->component);
        self::assertSame($routed['publishableComponent'], $routed['pageData']->publishableComponent);
        self::assertTrue($routed['page']->isTemplate);
        self::assertSame('test', $routed['group']->reference);
    }

    public function test_page_data_without_a_path_has_no_route(): void
    {
        static::createClient();

        $created = self::createPageDataWithRoute(null);

        self::assertNull($created['route']);
        self::assertInstanceOf(AbstractPageData::class, self::findByIri(self::iriOf($created['pageData'])));
    }
}
