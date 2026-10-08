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
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteReachabilityResolver;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Symfony\Bundle\SecurityBundle\Security;

class RouteReachabilityResolverTest extends TestCase
{
    private Security $security;
    private ObjectRepository $pageRepository;
    private ObjectRepository $pageDataRepository;
    private ?RouteReachabilityResolver $resolver = null;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->pageRepository = $this->createStub(ObjectRepository::class);
        $this->pageDataRepository = $this->createStub(ObjectRepository::class);
    }

    public function test_a_page_whose_own_route_is_granted_is_reachable(): void
    {
        $page = $this->createPage($this->createRoute());
        $this->grantAllRoutes();
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver()->isReachable($page));
    }

    public function test_a_page_whose_own_route_is_denied_and_which_has_no_children_is_not_reachable(): void
    {
        $page = $this->createPage($this->createRoute());
        $this->denyAllRoutes();
        $this->expectChildren([], []);

        self::assertFalse($this->resolver()->isReachable($page));
    }

    public function test_a_routeless_page_with_no_children_is_not_reachable(): void
    {
        $this->expectChildren([], []);

        self::assertFalse($this->resolver()->isReachable($this->createPage()));
    }

    public function test_a_routeless_page_is_reachable_when_a_child_page_has_a_granted_route(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage($this->createRoute());
        $this->grantAllRoutes();
        $this->expectChildren([$child], []);

        self::assertTrue($this->resolver()->isReachable($parent));
    }

    public function test_a_routeless_page_is_reachable_when_a_child_page_data_has_a_granted_route(): void
    {
        $parent = $this->createPage();
        $child = $this->createPageData($this->createRoute());
        $this->grantAllRoutes();
        $this->expectChildren([], [$child]);

        self::assertTrue($this->resolver()->isReachable($parent));
    }

    public function test_a_routeless_page_is_reachable_through_a_routeless_intermediate_child(): void
    {
        $parent = $this->createPage();
        $middle = $this->createPage();
        $leaf = $this->createPage($this->createRoute());

        $this->grantAllRoutes();
        [$pageRepository, $pageDataRepository] = $this->mockRepositories();
        $pageRepository->expects(self::exactly(2))->method('findBy')->willReturnCallback(static fn (array $criteria) => match ($criteria['parentPage'] ?? null) {
            $parent => [$middle],
            $middle => [$leaf],
            default => [],
        });
        $pageDataRepository->expects(self::exactly(2))->method('findBy')->willReturn([]);

        self::assertTrue($this->resolver()->isReachable($parent));
    }

    public function test_a_routeless_page_whose_only_descendant_route_is_denied_is_not_reachable(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage($this->createRoute());
        $this->denyAllRoutes();
        [$pageRepository, $pageDataRepository] = $this->mockRepositories();
        $pageRepository->expects(self::exactly(2))->method('findBy')->willReturnCallback(static fn (array $criteria) => ($criteria['parentPage'] ?? null) === $parent ? [$child] : []);
        $pageDataRepository->expects(self::exactly(2))->method('findBy')->willReturn([]);

        self::assertFalse($this->resolver()->isReachable($parent));
    }

    public function test_a_parent_chain_which_loops_back_on_itself_terminates(): void
    {
        $first = $this->createPage();
        $second = $this->createPage();

        $this->expectNoRouteCheck();
        [$pageRepository, $pageDataRepository] = $this->mockRepositories();
        $pageRepository->expects(self::exactly(2))->method('findBy')->willReturnCallback(static fn (array $criteria) => match ($criteria['parentPage'] ?? null) {
            $first => [$second],
            $second => [$first],
            default => [],
        });
        $pageDataRepository->expects(self::exactly(2))->method('findBy')->willReturn([]);

        self::assertFalse($this->resolver()->isReachable($first));
    }

    public function test_a_page_with_no_id_is_not_queried_for_children(): void
    {
        $page = new Page();
        $page->isTemplate = false;
        $this->expectNoChildLookup();

        self::assertFalse($this->resolver()->isReachable($page));
    }

    public function test_the_answer_for_a_page_is_resolved_once_per_request(): void
    {
        $page = $this->createPage($this->createRoute());
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->willReturn(true);
        $this->security = $security;
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver()->isReachable($page));
        self::assertTrue($this->resolver()->isReachable($page));
    }

    public function test_a_routeless_page_marked_reachable_without_a_route_with_no_parent_is_reachable(): void
    {
        $page = $this->createPage(reachableWithoutRoute: true);
        $this->expectNoRouteCheck();
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver()->isReachable($page));
    }

    public function test_the_flag_is_ignored_for_a_page_whose_own_route_is_denied(): void
    {
        $page = $this->createPage($this->createRoute(null), reachableWithoutRoute: true);
        $this->denyAllRoutes();
        $this->expectChildren([], []);

        self::assertFalse($this->resolver()->isReachable($page));
    }

    public function test_a_page_marked_reachable_without_a_route_is_reachable_when_its_nearest_routed_ancestor_is_granted(): void
    {
        $parentRoute = $this->createRoute();
        $grandParent = $this->createPage($this->createRoute());
        $parent = $this->createPage($parentRoute);
        $parent->setParentPage($grandParent);
        $page = $this->createPage(reachableWithoutRoute: true);
        $page->setParentPage($parent);

        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->with(RouteVoter::READ_ROUTE, self::identicalTo($parentRoute))->willReturn(true);
        $this->security = $security;
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver()->isReachable($page));
    }

    public function test_a_page_marked_reachable_without_a_route_is_not_reachable_when_its_nearest_routed_ancestor_is_denied(): void
    {
        $parent = $this->createPage($this->createRoute());
        $page = $this->createPage(reachableWithoutRoute: true);
        $page->setParentPage($parent);
        $this->denyAllRoutes();
        $this->expectChildren([], []);

        self::assertFalse($this->resolver()->isReachable($page));
    }

    public function test_a_page_marked_reachable_without_a_route_finds_a_routed_ancestor_above_a_routeless_parent_page_data(): void
    {
        $grandParentRoute = $this->createRoute();
        $grandParent = $this->createPage($grandParentRoute);
        $parent = $this->createPageData();
        $parent->setParentPage($grandParent);
        $page = $this->createPage(reachableWithoutRoute: true);
        $page->setParentPageData($parent);

        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->with(RouteVoter::READ_ROUTE, self::identicalTo($grandParentRoute))->willReturn(false);
        $this->security = $security;
        $this->expectChildren([], []);

        self::assertFalse($this->resolver()->isReachable($page));
    }

    public function test_a_page_marked_reachable_without_a_route_whose_routeless_ancestors_loop_back_is_reachable(): void
    {
        $page = $this->createPage(reachableWithoutRoute: true);
        $parent = $this->createPage();
        $page->setParentPage($parent);
        $parent->setParentPage($page);
        $this->expectNoRouteCheck();
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver()->isReachable($page));
    }

    public function test_a_routeless_page_is_reachable_when_a_child_page_is_marked_reachable_without_a_route(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage(reachableWithoutRoute: true);
        $child->setParentPage($parent);
        $this->expectNoRouteCheck();
        $this->expectChildren([$child], []);

        self::assertTrue($this->resolver()->isReachable($parent));
    }

    private function resolver(): RouteReachabilityResolver
    {
        if (null === $this->resolver) {
            $manager = $this->createStub(ObjectManager::class);
            $manager->method('getRepository')->willReturnCallback(fn (string $class) => Page::class === $class ? $this->pageRepository : $this->pageDataRepository);

            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturn($manager);

            $this->resolver = new RouteReachabilityResolver($registry, $this->security);
        }

        return $this->resolver;
    }

    /**
     * @return array{ObjectRepository&MockObject, ObjectRepository&MockObject}
     */
    private function mockRepositories(): array
    {
        $pageRepository = $this->createMock(ObjectRepository::class);
        $pageDataRepository = $this->createMock(ObjectRepository::class);
        $this->pageRepository = $pageRepository;
        $this->pageDataRepository = $pageDataRepository;

        return [$pageRepository, $pageDataRepository];
    }

    private function grantAllRoutes(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::atLeastOnce())->method('isGranted')->with(RouteVoter::READ_ROUTE, self::anything())->willReturn(true);
        $this->security = $security;
    }

    private function denyAllRoutes(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::atLeastOnce())->method('isGranted')->with(RouteVoter::READ_ROUTE, self::anything())->willReturn(false);
        $this->security = $security;
    }

    private function expectNoRouteCheck(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');
        $this->security = $security;
    }

    /**
     * @param AbstractPage[] $pages
     * @param AbstractPage[] $pageData
     */
    private function expectChildren(array $pages, array $pageData): void
    {
        [$pageRepository, $pageDataRepository] = $this->mockRepositories();
        $pageRepository->expects(self::once())->method('findBy')->willReturn($pages);
        $pageDataRepository->expects(self::once())->method('findBy')->willReturn($pageData);
    }

    private function expectNoChildLookup(): void
    {
        [$pageRepository, $pageDataRepository] = $this->mockRepositories();
        $pageRepository->expects(self::never())->method('findBy');
        $pageDataRepository->expects(self::never())->method('findBy');
    }

    private function createRoute(?\DateTimeImmutable $liveAt = new \DateTimeImmutable('-1 day')): Route
    {
        return (new Route())->setLiveAt($liveAt);
    }

    private function createPage(?Route $route = null, bool $reachableWithoutRoute = false): Page
    {
        $page = new Page();
        $page->isTemplate = false;
        $page->isReachableWithoutRoute = $reachableWithoutRoute;
        $page->setRoute($route);

        return $this->withId($page);
    }

    private function createPageData(?Route $route = null): AbstractPageData
    {
        $pageData = new PageData();
        $pageData->setRoute($route);

        return $this->withId($pageData);
    }

    /**
     * @template T of AbstractPage
     *
     * @param T $page
     *
     * @return T
     */
    private function withId(AbstractPage $page): AbstractPage
    {
        $reflection = new \ReflectionProperty($page, 'id');
        $reflection->setValue($page, Uuid::uuid4());

        return $page;
    }
}
