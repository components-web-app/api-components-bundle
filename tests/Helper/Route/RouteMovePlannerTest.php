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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteCascadePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RouteMovePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RoutePrefixResolver;
use Silverback\ApiComponentsBundle\Validator\Constraints\RoutePathStart;

class RouteMovePlannerTest extends TestCase
{
    use RouteHierarchyStubTrait;

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function movedPaths(): iterable
    {
        yield 'the part after the old start goes under the new start' => ['/blog/my-post', '/blog/', '/news/', '/news/my-post'];
        yield 'several segments after the old start are kept' => ['/blog/2026/my-post', '/blog/', '/news/', '/news/2026/my-post'];
        yield 'no old start is the site root' => ['/about/team', null, '/company/', '/company/about/team'];
        yield 'no new start is the site root' => ['/blog/my-post', '/blog/', null, '/my-post'];
        yield 'a path outside its old start keeps its last segment' => ['/legacy/archive/my-post', '/blog/', '/news/', '/news/my-post'];
        yield 'a path that is its old start keeps its last segment' => ['/blog/', '/blog/', '/news/', '/news/'];
    }

    #[DataProvider('movedPaths')]
    public function test_the_moved_path(string $oldPath, ?string $oldStart, ?string $newStart, string $expected): void
    {
        self::assertSame($expected, RouteMovePlanner::movedPath($oldPath, $oldStart, $newStart));
    }

    public function test_a_moved_routed_page_takes_the_new_parent_path_and_its_descendants_follow(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $news = self::routed(new Page(), '/news');
        $post = self::routed((new Page())->setParentPage($news), '/blog/post');
        $comments = self::routed((new Page())->setParentPage($post), '/blog/post/comments');

        $nodes = $this->planner()->plan($post, $blog, $this->hierarchy([$blog, $news, $post, $comments]));

        self::assertCount(1, $nodes);
        self::assertSame(self::routeOf($post), $nodes[0]['route']);
        self::assertSame(['/blog/post', '/news/post'], [$nodes[0]['oldPath'], $nodes[0]['newPath']]);
        self::assertCount(1, $nodes[0]['children']);
        self::assertSame(['/blog/post/comments', '/news/post/comments'], [$nodes[0]['children'][0]['oldPath'], $nodes[0]['children'][0]['newPath']]);
    }

    public function test_moved_prefixed_page_data_keeps_its_prefix(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $news = self::routed(new Page(), '/news');
        $announcement = self::routed(self::prefixedPageData()->setParentPage($news), '/blog/announcements/launch');

        $nodes = $this->planner()->plan($announcement, $blog, $this->hierarchy([$blog, $news, $announcement]));

        self::assertSame('/news/announcements/launch', $nodes[0]['newPath']);
    }

    public function test_the_old_start_is_found_through_unrouted_ancestors_of_the_original_parent(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $section = (new Page())->setParentPage($blog);
        $post = self::routed(new Page(), '/blog/post');

        $nodes = $this->planner()->plan($post, $section, $this->hierarchy([$blog, $section, $post]));

        self::assertSame('/post', $nodes[0]['newPath']);
    }

    public function test_a_move_that_keeps_the_path_plans_nothing(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $section = (new Page())->setParentPage($blog);
        $post = self::routed((new Page())->setParentPage($section), '/blog/post');
        $child = self::routed((new Page())->setParentPage($post), '/blog/post/child');

        self::assertSame([], $this->planner()->plan($post, $blog, $this->hierarchy([$blog, $section, $post, $child])));
    }

    public function test_a_moved_page_with_no_route_moves_its_nearest_routed_descendants_by_their_own_required_start(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $news = self::routed(new Page(), '/news');
        $section = (new Page())->setParentPage($news);
        $child = self::routed((new Page())->setParentPage($section), '/blog/child');
        $announcement = self::routed(self::prefixedPageData()->setParentPage($section), '/blog/announcements/launch');
        $grandchild = self::routed((new Page())->setParentPage($child), '/blog/child/grandchild');

        $nodes = $this->planner()->plan($section, $blog, $this->hierarchy([$blog, $news, $section, $child, $announcement, $grandchild]));

        self::assertSame(
            [['/blog/child', '/news/child'], ['/blog/announcements/launch', '/news/announcements/launch']],
            array_map(static fn (array $node): array => [$node['oldPath'], $node['newPath']], $nodes),
        );
        self::assertSame('/news/child/grandchild', $nodes[0]['children'][0]['newPath']);
    }

    public function test_a_moved_page_with_no_route_and_unchanged_descendants_plans_nothing(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $other = (new Page())->setParentPage($blog);
        $section = (new Page())->setParentPage($other);
        $child = self::routed((new Page())->setParentPage($section), '/blog/child');

        self::assertSame([], $this->planner()->plan($section, $blog, $this->hierarchy([$blog, $other, $section, $child])));
    }

    public function test_violations_carry_the_move_message_on_the_given_property(): void
    {
        $root = new \stdClass();
        $nodes = [self::node('/blog/post', '/news/post')];

        $violations = $this->planner()->violations($nodes, $root, 'parentPageData', $this->hierarchy([], ['/news/post']));

        self::assertCount(1, $violations);
        self::assertSame(\sprintf(RouteMovePlanner::MESSAGE, '/blog/post', '/news/post', RouteCascadePlanner::TAKEN_MESSAGE), $violations[0]->getMessage());
        self::assertSame('parentPageData', $violations[0]->getPropertyPath());
        self::assertSame($root, $violations[0]->getRoot());
    }

    public function test_a_new_path_that_ends_at_its_start_is_a_path_start_violation(): void
    {
        $root = new \stdClass();

        $violations = $this->planner()->violations([self::node('/a', '/b'), self::node('/blog/', '/news/')], $root, 'parentPage', $this->hierarchy([]));

        self::assertCount(1, $violations);
        self::assertSame('Moving this page would change the route "/blog/" to "/news/". This path must start with "/news/" and continue after it.', $violations[0]->getMessage());
        self::assertSame('parentPage', $violations[0]->getPropertyPath());
        self::assertSame($root, $violations[0]->getRoot());
        self::assertSame('/news/', $violations[0]->getInvalidValue());
        self::assertSame(RoutePathStart::PATH_OUTSIDE_START_ERROR, $violations[0]->getCode());
        self::assertInstanceOf(RoutePathStart::class, $violations[0]->getConstraint());
    }

    public function test_the_site_root_and_paths_not_ending_in_a_slash_are_no_violation(): void
    {
        self::assertCount(0, $this->planner()->violations([self::node('/blog', '/'), self::node('/a', '/b')], new \stdClass(), 'parentPage', $this->hierarchy([])));
    }

    private function planner(): RouteMovePlanner
    {
        return new RouteMovePlanner(
            new RoutePrefixResolver(new RoutePrefixAttributeReader($this->createStub(ManagerRegistry::class))),
            new RouteCascadePlanner($this->redirectingGenerator(), self::validator()),
        );
    }

    private static function prefixedPageData(): AbstractPageData
    {
        return new #[RoutePrefix('/announcements')] class extends AbstractPageData {};
    }

    /**
     * @return array{route: Route, oldPath: string, newPath: string, children: list<mixed>}
     */
    private static function node(string $oldPath, string $newPath): array
    {
        return ['route' => (new Route())->setPath($oldPath), 'oldPath' => $oldPath, 'newPath' => $newPath, 'children' => []];
    }
}
