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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteCascadePlanner;
use Symfony\Component\Validator\Constraints\Length;

class RouteCascadePlannerTest extends TestCase
{
    use RouteHierarchyStubTrait;

    private const string FORMAT = 'Route "%s" to "%s": %s';

    public function test_a_node_carries_the_route_its_old_and_new_path_and_its_planned_descendants(): void
    {
        $parent = self::routed(new Page(), '/old');
        $child = self::routed((new Page())->setParentPage($parent), '/old/child');

        $node = $this->planner()->node(self::routeOf($parent), '/new', $this->hierarchy([$parent, $child]));

        self::assertSame(self::routeOf($parent), $node['route']);
        self::assertSame('/old', $node['oldPath']);
        self::assertSame('/new', $node['newPath']);
        self::assertSame([['/old/child', '/new/child', []]], self::summary($node['children']));
    }

    public function test_a_node_for_a_route_with_no_page_has_no_descendants(): void
    {
        $route = (new Route())->setPath('/old');

        $node = $this->planner()->node($route, '/new', $this->hierarchy([]));

        self::assertSame([], $node['children']);
    }

    public function test_descendants_under_the_old_path_take_the_new_path_through_unrouted_pages_and_page_data(): void
    {
        $parent = self::routed(new Page(), '/old');
        $child = self::routed((new Page())->setParentPage($parent), '/old/child');
        $grandchild = self::routed(self::pageData()->setParentPage($child), '/old/child/data');
        $unrouted = (new Page())->setParentPage($parent);
        $underUnrouted = self::routed((new Page())->setParentPage($unrouted), '/old/under');
        $outside = self::routed((new Page())->setParentPage($parent), '/elsewhere/page');
        $underOutside = self::routed((new Page())->setParentPage($outside), '/old/under-outside');

        $nodes = $this->planner()->planDescendants($parent, '/old', '/new', $this->hierarchy([$parent, $child, $grandchild, $unrouted, $underUnrouted, $outside, $underOutside]));

        self::assertSame([
            ['/old/child', '/new/child', [['/old/child/data', '/new/child/data', []]]],
            ['/old/under', '/new/under', []],
        ], self::summary($nodes));
    }

    public function test_children_of_page_data_are_found_by_their_parent_page_data(): void
    {
        $parent = self::routed(self::pageData(), '/old');
        $child = self::routed((new Page())->setParentPageData($parent), '/old/child');

        $nodes = $this->planner()->planDescendants($parent, '/old', '/new', $this->hierarchy([$parent, $child]));

        self::assertSame([['/old/child', '/new/child', []]], self::summary($nodes));
    }

    public function test_a_path_that_only_shares_the_old_paths_characters_is_not_a_descendant(): void
    {
        $parent = self::routed(new Page(), '/old');
        $child = self::routed((new Page())->setParentPage($parent), '/older');

        self::assertSame([], $this->planner()->planDescendants($parent, '/old', '/new', $this->hierarchy([$parent, $child])));
    }

    public function test_a_descendant_that_loops_back_to_the_starting_page_is_not_planned_again(): void
    {
        $parent = self::routed(new Page(), '/old');
        $unrouted = (new Page())->setParentPage($parent);
        $parent->setParentPage($unrouted);

        self::assertSame([], $this->planner()->planDescendants($parent, '/old', '/new', $this->hierarchy([$parent, $unrouted])));
    }

    public function test_the_nearest_routed_descendants_take_the_path_they_are_given_and_their_descendants_follow(): void
    {
        $moved = new Page();
        $child = self::routed((new Page())->setParentPage($moved), '/old/child');
        $grandchild = self::routed((new Page())->setParentPage($child), '/old/child/grandchild');
        $unrouted = (new Page())->setParentPage($moved);
        $underUnrouted = self::routed((new Page())->setParentPage($unrouted), '/old/under');
        $given = [];

        $nodes = $this->planner()->planNearestRoutedDescendants($moved, static function (AbstractPage $page, Route $route) use (&$given): string {
            $given[] = [$page, $route->getPath()];

            return '/new' . substr($route->getPath(), 4);
        }, $this->hierarchy([$moved, $child, $grandchild, $unrouted, $underUnrouted]));

        self::assertSame([
            ['/old/child', '/new/child', [['/old/child/grandchild', '/new/child/grandchild', []]]],
            ['/old/under', '/new/under', []],
        ], self::summary($nodes));
        self::assertSame([[$child, '/old/child'], [$underUnrouted, '/old/under']], $given);
    }

    public function test_a_nearest_routed_descendant_whose_path_is_unchanged_is_left_out_with_its_descendants(): void
    {
        $moved = new Page();
        $child = self::routed((new Page())->setParentPage($moved), '/same');
        $grandchild = self::routed((new Page())->setParentPage($child), '/same/grandchild');
        $unrouted = (new Page())->setParentPage($moved);
        $changed = self::routed((new Page())->setParentPage($moved), '/old/changed');
        $underUnrouted = self::routed((new Page())->setParentPage($unrouted), '/old/under');

        $nodes = $this->planner()->planNearestRoutedDescendants(
            $moved,
            static fn (AbstractPage $page, Route $route): string => str_replace('/old/', '/new/', $route->getPath()),
            $this->hierarchy([$moved, $unrouted, $child, $grandchild, $changed, $underUnrouted]),
        );

        self::assertSame([['/old/changed', '/new/changed', []], ['/old/under', '/new/under', []]], self::summary($nodes));
    }

    public function test_the_nearest_routed_descendants_never_include_the_moved_page_again(): void
    {
        $moved = self::routed(new Page(), '/moved');
        $unrouted = (new Page())->setParentPage($moved);
        $moved->setParentPage($unrouted);

        self::assertSame([], $this->planner()->planNearestRoutedDescendants($moved, static fn (): string => '/new', $this->hierarchy([$moved, $unrouted])));
    }

    public function test_a_planned_path_that_is_too_long_or_taken_is_a_violation_on_the_given_root_and_property(): void
    {
        $root = new \stdClass();
        $long = '/' . str_repeat('a', 255);
        $nodes = [self::node('/old', '/taken', [self::node('/old/child', $long, [])])];

        $violations = $this->planner()->violations($nodes, $root, 'parentPage', self::FORMAT, $this->hierarchy([], ['/taken']));

        self::assertCount(2, $violations);
        self::assertSame('Route "/old" to "/taken": ' . RouteCascadePlanner::TAKEN_MESSAGE, $violations[0]->getMessage());
        self::assertSame('parentPage', $violations[0]->getPropertyPath());
        self::assertSame($root, $violations[0]->getRoot());
        self::assertSame('/taken', $violations[0]->getInvalidValue());
        self::assertNull($violations[0]->getCode());
        self::assertNull($violations[0]->getConstraint());
        self::assertStringStartsWith(\sprintf('Route "/old/child" to "%s": This value is too long.', $long), (string) $violations[1]->getMessage());
        self::assertSame('parentPage', $violations[1]->getPropertyPath());
        self::assertSame($root, $violations[1]->getRoot());
        self::assertSame($long, $violations[1]->getInvalidValue());
        self::assertSame(Length::TOO_LONG_ERROR, $violations[1]->getCode());
        self::assertInstanceOf(Length::class, $violations[1]->getConstraint());
    }

    public function test_a_planned_path_that_is_both_too_long_and_taken_is_two_violations(): void
    {
        $long = '/' . str_repeat('a', 255);

        $violations = $this->planner()->violations([self::node('/old', $long, [])], new \stdClass(), 'path', self::FORMAT, $this->hierarchy([], [$long]));

        self::assertCount(2, $violations);
        self::assertSame(Length::TOO_LONG_ERROR, $violations[0]->getCode());
        self::assertSame(\sprintf('Route "/old" to "%s": %s', $long, RouteCascadePlanner::TAKEN_MESSAGE), $violations[1]->getMessage());
    }

    public function test_planned_paths_that_are_free_and_valid_are_no_violation(): void
    {
        $nodes = [self::node('/old', '/new', [self::node('/old/child', '/new/child', [])])];

        self::assertCount(0, $this->planner()->violations($nodes, new \stdClass(), 'path', self::FORMAT, $this->hierarchy([], ['/old', '/old/child'])));
    }

    public function test_applying_rewrites_each_level_flushes_it_and_then_redirects_its_old_paths(): void
    {
        $route = (new Route())->setPath('/old')->setName('old');
        $child = (new Route())->setPath('/old/child')->setName('old-child');
        $nodes = [['route' => $route, 'oldPath' => '/old', 'newPath' => '/new', 'children' => [['route' => $child, 'oldPath' => '/old/child', 'newPath' => '/new/child', 'children' => []]]]];
        $entityManager = $this->mockHierarchy([]);
        $calls = [];
        $entityManager->expects(self::exactly(2))->method('flush')->willReturnCallback(static function () use (&$calls, $route, $child): void {
            $calls[] = ['flush', $route->getPath(), $child->getPath()];
        });
        $entityManager->method('persist')->willReturnCallback(static function (Route $redirect) use (&$calls): void {
            $calls[] = ['redirect', $redirect->getPath(), $redirect->getRedirect()?->getPath()];
        });

        $this->planner()->apply($nodes, $entityManager);

        self::assertSame([
            ['flush', '/new', '/old/child'],
            ['redirect', '/old', '/new'],
            ['flush', '/new', '/new/child'],
            ['redirect', '/old/child', '/new/child'],
        ], $calls);
        self::assertSame('/new', $route->getName());
        self::assertSame('/new/child', $child->getName());
    }

    public function test_applying_nothing_writes_nothing(): void
    {
        $entityManager = $this->mockHierarchy([]);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('persist');

        $this->planner()->apply([], $entityManager);
    }

    private function planner(): RouteCascadePlanner
    {
        return new RouteCascadePlanner($this->redirectingGenerator(), self::validator());
    }

    private static function pageData(): AbstractPageData
    {
        return new class extends AbstractPageData {};
    }

    /**
     * @param list<array{route: Route, oldPath: string, newPath: string, children: list<mixed>}> $children
     *
     * @return array{route: Route, oldPath: string, newPath: string, children: list<mixed>}
     */
    private static function node(string $oldPath, string $newPath, array $children): array
    {
        return ['route' => (new Route())->setPath($oldPath), 'oldPath' => $oldPath, 'newPath' => $newPath, 'children' => $children];
    }

    /**
     * @param list<mixed> $nodes
     *
     * @return list<array{string, string, list<mixed>}>
     */
    private static function summary(array $nodes): array
    {
        return array_map(static fn (array $node): array => [$node['oldPath'], $node['newPath'], self::summary($node['children'])], $nodes);
    }
}
