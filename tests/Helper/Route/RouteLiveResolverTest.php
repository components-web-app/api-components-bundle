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

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteLiveResolver;

class RouteLiveResolverTest extends TestCase
{
    private RouteLiveResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RouteLiveResolver();
    }

    public function test_a_route_without_a_page_resolves_to_its_own_live_at(): void
    {
        $liveAt = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $route = $this->createRoute($liveAt);

        self::assertEquals($liveAt, $this->resolver->resolveEffectiveLiveAt($route));
    }

    public function test_a_route_with_a_null_live_at_resolves_to_null(): void
    {
        $route = $this->createRoute(null);

        self::assertNull($this->resolver->resolveEffectiveLiveAt($route));
    }

    public function test_a_route_under_an_unrouted_parent_page_resolves_to_its_own_live_at(): void
    {
        $liveAt = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $parentPage = $this->createPage();
        $childPage = $this->createPage();
        $childPage->setParentPage($parentPage);
        $route = $this->createRoute($liveAt, $childPage);

        self::assertEquals($liveAt, $this->resolver->resolveEffectiveLiveAt($route));
    }

    public function test_a_route_under_a_draft_parent_route_resolves_to_null(): void
    {
        $parentPage = $this->createPage();
        $this->createRoute(null, $parentPage);

        $childPage = $this->createPage();
        $childPage->setParentPage($parentPage);
        $childRoute = $this->createRoute(new \DateTimeImmutable('2020-01-01T00:00:00+00:00'), $childPage);

        self::assertNull($this->resolver->resolveEffectiveLiveAt($childRoute));
    }

    public function test_the_latest_date_in_the_chain_wins_when_the_parent_is_later(): void
    {
        $parentLiveAt = new \DateTimeImmutable('2999-01-01T00:00:00+00:00');
        $parentPage = $this->createPage();
        $this->createRoute($parentLiveAt, $parentPage);

        $childPage = $this->createPage();
        $childPage->setParentPage($parentPage);
        $childRoute = $this->createRoute(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $childPage);

        self::assertEquals($parentLiveAt, $this->resolver->resolveEffectiveLiveAt($childRoute));
    }

    public function test_the_latest_date_in_the_chain_wins_when_the_child_is_later(): void
    {
        $childLiveAt = new \DateTimeImmutable('2999-01-01T00:00:00+00:00');
        $parentPage = $this->createPage();
        $this->createRoute(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $parentPage);

        $childPage = $this->createPage();
        $childPage->setParentPage($parentPage);
        $childRoute = $this->createRoute($childLiveAt, $childPage);

        self::assertEquals($childLiveAt, $this->resolver->resolveEffectiveLiveAt($childRoute));
    }

    public function test_an_unrouted_ancestor_is_skipped_and_the_walk_continues_to_a_routed_grandparent(): void
    {
        $grandParentLiveAt = new \DateTimeImmutable('2999-01-01T00:00:00+00:00');
        $grandParentPage = $this->createPage();
        $this->createRoute($grandParentLiveAt, $grandParentPage);

        $unroutedParent = $this->createPage();
        $unroutedParent->setParentPage($grandParentPage);

        $childPage = $this->createPage();
        $childPage->setParentPage($unroutedParent);
        $childRoute = $this->createRoute(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $childPage);

        self::assertEquals($grandParentLiveAt, $this->resolver->resolveEffectiveLiveAt($childRoute));
    }

    public function test_a_circular_parent_chain_terminates(): void
    {
        $liveAt = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $pageA = $this->createPage();
        $pageB = $this->createPage();
        $pageA->setParentPage($pageB);
        $pageB->setParentPage($pageA);
        $route = $this->createRoute($liveAt, $pageA);

        self::assertEquals($liveAt, $this->resolver->resolveEffectiveLiveAt($route));
    }

    public function test_is_live_resolves_the_effective_date(): void
    {
        $route = new Route();

        $route->setLiveAt(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'));
        self::assertTrue($this->resolver->isLive($route));

        $route->setLiveAt(new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));
        self::assertFalse($this->resolver->isLive($route));

        $route->setLiveAt(null);
        self::assertFalse($this->resolver->isLive($route));
    }

    public function test_is_live_follows_an_ancestor_which_is_not_yet_live(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage();
        $child->setParentPage($parent);

        $parentRoute = $this->createRoute(new \DateTimeImmutable('2999-01-01T00:00:00+00:00'), $parent);
        $parent->setRoute($parentRoute);

        $route = $this->createRoute(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $child);

        self::assertFalse($this->resolver->isLive($route));
    }

    public function test_a_resolved_answer_is_recomputed_when_the_route_own_date_changes(): void
    {
        $route = new Route();

        $route->setLiveAt(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'));
        self::assertTrue($this->resolver->isLive($route));

        $route->setLiveAt(new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));
        self::assertFalse($this->resolver->isLive($route));
    }

    private function createPage(): Page
    {
        $page = new Page();
        $this->assignId($page);

        return $page;
    }

    private function createRoute(?\DateTimeImmutable $liveAt, ?Page $page = null): Route
    {
        $route = new Route();
        $route->setLiveAt($liveAt);
        if ($page) {
            $route->setPage($page);
        }

        return $route;
    }

    private function assignId(AbstractPage $page): void
    {
        $property = new \ReflectionProperty($page, 'id');
        $property->setValue($page, Uuid::uuid4());
    }
}
