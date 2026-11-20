<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Route;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RoutePrefixResolver;

class RoutePrefixResolverTest extends TestCase
{
    private RoutePrefixResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RoutePrefixResolver(new RoutePrefixAttributeReader($this->createStub(ManagerRegistry::class)));
    }

    public function test_a_page_with_no_parent_has_no_required_start(): void
    {
        self::assertNull($this->resolver->requiredStart(new Page()));
    }

    public function test_page_data_without_a_prefix_and_no_parent_has_no_required_start(): void
    {
        self::assertNull($this->resolver->requiredStart(self::pageData()));
    }

    public function test_prefixed_page_data_at_the_root_must_start_with_its_prefix_and_a_slash(): void
    {
        self::assertSame('/announcements/', $this->resolver->requiredStart(self::prefixedPageData()));
    }

    public function test_a_page_under_a_routed_parent_page_must_start_with_the_parent_path_and_a_slash(): void
    {
        $child = (new Page())->setParentPage(self::routedPage('/conference'));

        self::assertSame('/conference/', $this->resolver->requiredStart($child));
    }

    public function test_a_page_under_routed_page_data_must_start_with_its_path_and_a_slash(): void
    {
        $parent = self::prefixedPageData()->setRoute(self::route('/announcements/launch'));
        $child = (new Page())->setParentPageData($parent);

        self::assertSame('/announcements/launch/', $this->resolver->requiredStart($child));
    }

    public function test_prefixed_page_data_under_a_routed_parent_must_start_with_the_parent_path_then_its_prefix(): void
    {
        $child = self::prefixedPageData()->setParentPage(self::routedPage('/news'));

        self::assertSame('/news/announcements/', $this->resolver->requiredStart($child));
    }

    public function test_prefixes_compose_down_a_chain_of_prefixed_page_data(): void
    {
        $parent = self::prefixedPageData()->setRoute(self::route('/announcements/launch'));
        $child = self::updatesPageData()->setParentPageData($parent);

        self::assertSame('/announcements/launch/updates/', $this->resolver->requiredStart($child));
    }

    public function test_an_unrouted_ancestor_is_skipped_for_the_nearest_routed_one(): void
    {
        $unrouted = (new Page())->setParentPage(self::routedPage('/conference'));
        $child = (new Page())->setParentPage($unrouted);

        self::assertSame('/conference/', $this->resolver->requiredStart($child));
    }

    public function test_the_prefix_of_an_unrouted_ancestor_is_dropped(): void
    {
        $unrouted = self::prefixedPageData()->setParentPage(self::routedPage('/news'));
        $child = self::updatesPageData()->setParentPageData($unrouted);

        self::assertSame('/news/updates/', $this->resolver->requiredStart($child));
    }

    public function test_a_chain_of_unrouted_ancestors_leaves_no_required_start_without_a_prefix(): void
    {
        $child = (new Page())->setParentPage((new Page())->setParentPage(new Page()));

        self::assertNull($this->resolver->requiredStart($child));
    }

    public function test_a_chain_of_unrouted_ancestors_leaves_only_the_prefix(): void
    {
        $child = self::prefixedPageData()->setParentPage((new Page())->setParentPage(new Page()));

        self::assertSame('/announcements/', $this->resolver->requiredStart($child));
    }

    public function test_a_parent_cycle_of_unrouted_pages_ends_the_walk(): void
    {
        $first = new Page();
        $second = (new Page())->setParentPage($first);
        $first->setParentPage($second);

        self::assertNull($this->resolver->requiredStart($first));
    }

    public function test_a_cycle_back_to_the_page_itself_never_uses_its_own_route(): void
    {
        $page = self::routedPage('/self');
        $parent = (new Page())->setParentPage($page);
        $page->setParentPage($parent);

        self::assertNull($this->resolver->requiredStart($page));
    }

    public function test_a_routed_parent_at_the_site_root_requires_only_a_slash(): void
    {
        $child = (new Page())->setParentPage(self::routedPage('/'));

        self::assertSame('/', $this->resolver->requiredStart($child));
    }

    public function test_a_prefixed_child_of_the_site_root_requires_its_prefix(): void
    {
        $child = self::prefixedPageData()->setParentPage(self::routedPage('/'));

        self::assertSame('/announcements/', $this->resolver->requiredStart($child));
    }

    public function test_the_parent_page_is_preferred_over_the_parent_page_data(): void
    {
        $child = (new Page())
            ->setParentPage(self::routedPage('/page'))
            ->setParentPageData(self::pageData()->setRoute(self::route('/page-data')));

        self::assertSame('/page/', $this->resolver->requiredStart($child));
    }

    public function test_an_unrouted_ancestors_parent_page_is_preferred_over_its_parent_page_data(): void
    {
        $unrouted = (new Page())
            ->setParentPage(self::routedPage('/page'))
            ->setParentPageData(self::pageData()->setRoute(self::route('/page-data')));
        $child = (new Page())->setParentPage($unrouted);

        self::assertSame('/page/', $this->resolver->requiredStart($child));
    }

    private static function routedPage(string $path): Page
    {
        return (new Page())->setRoute(self::route($path));
    }

    private static function route(string $path): Route
    {
        return (new Route())->setName($path)->setPath($path);
    }

    private static function pageData(): AbstractPageData
    {
        return new class extends AbstractPageData {};
    }

    private static function prefixedPageData(): AbstractPageData
    {
        return new #[RoutePrefix('/announcements')] class extends AbstractPageData {};
    }

    private static function updatesPageData(): AbstractPageData
    {
        return new #[RoutePrefix('/updates')] class extends AbstractPageData {};
    }
}
