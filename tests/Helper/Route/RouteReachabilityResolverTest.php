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
    private Security&MockObject $security;
    private ObjectRepository&MockObject $pageRepository;
    private ObjectRepository&MockObject $pageDataRepository;
    private RouteReachabilityResolver $resolver;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->pageRepository = $this->createMock(ObjectRepository::class);
        $this->pageDataRepository = $this->createMock(ObjectRepository::class);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->willReturnCallback(fn (string $class) => Page::class === $class ? $this->pageRepository : $this->pageDataRepository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $this->resolver = new RouteReachabilityResolver($registry, $this->security);
    }

    public function test_a_page_whose_own_route_is_granted_is_reachable(): void
    {
        $page = $this->createPage($this->createRoute());
        $this->grantAllRoutes();
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver->isReachable($page));
    }

    public function test_a_page_whose_own_route_is_denied_and_which_has_no_children_is_not_reachable(): void
    {
        $page = $this->createPage($this->createRoute());
        $this->denyAllRoutes();
        $this->expectChildren([], []);

        self::assertFalse($this->resolver->isReachable($page));
    }

    public function test_a_routeless_page_with_no_children_is_not_reachable(): void
    {
        $this->expectChildren([], []);

        self::assertFalse($this->resolver->isReachable($this->createPage()));
    }

    public function test_a_routeless_page_is_reachable_when_a_child_page_has_a_granted_route(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage($this->createRoute());
        $this->grantAllRoutes();
        $this->expectChildren([$child], []);

        self::assertTrue($this->resolver->isReachable($parent));
    }

    public function test_a_routeless_page_is_reachable_when_a_child_page_data_has_a_granted_route(): void
    {
        $parent = $this->createPage();
        $child = $this->createPageData($this->createRoute());
        $this->grantAllRoutes();
        $this->expectChildren([], [$child]);

        self::assertTrue($this->resolver->isReachable($parent));
    }

    public function test_a_routeless_page_is_reachable_through_a_routeless_intermediate_child(): void
    {
        $parent = $this->createPage();
        $middle = $this->createPage();
        $leaf = $this->createPage($this->createRoute());

        $this->grantAllRoutes();
        $this->pageRepository->method('findBy')->willReturnCallback(static fn (array $criteria) => match ($criteria['parentPage'] ?? null) {
            $parent => [$middle],
            $middle => [$leaf],
            default => [],
        });
        $this->pageDataRepository->method('findBy')->willReturn([]);

        self::assertTrue($this->resolver->isReachable($parent));
    }

    public function test_a_routeless_page_whose_only_descendant_route_is_denied_is_not_reachable(): void
    {
        $parent = $this->createPage();
        $child = $this->createPage($this->createRoute());
        $this->denyAllRoutes();
        $this->pageRepository->method('findBy')->willReturnCallback(static fn (array $criteria) => ($criteria['parentPage'] ?? null) === $parent ? [$child] : []);
        $this->pageDataRepository->method('findBy')->willReturn([]);

        self::assertFalse($this->resolver->isReachable($parent));
    }

    public function test_a_parent_chain_which_loops_back_on_itself_terminates(): void
    {
        $first = $this->createPage();
        $second = $this->createPage();

        $this->denyAllRoutes();
        $this->pageRepository->method('findBy')->willReturnCallback(static fn (array $criteria) => match ($criteria['parentPage'] ?? null) {
            $first => [$second],
            $second => [$first],
            default => [],
        });
        $this->pageDataRepository->method('findBy')->willReturn([]);

        self::assertFalse($this->resolver->isReachable($first));
    }

    public function test_a_page_with_no_id_is_not_queried_for_children(): void
    {
        $page = new Page();
        $page->isTemplate = false;
        $this->expectNoChildLookup();

        self::assertFalse($this->resolver->isReachable($page));
    }

    public function test_the_answer_for_a_page_is_resolved_once_per_request(): void
    {
        $page = $this->createPage($this->createRoute());
        $this->security->expects(self::once())->method('isGranted')->willReturn(true);
        $this->expectNoChildLookup();

        self::assertTrue($this->resolver->isReachable($page));
        self::assertTrue($this->resolver->isReachable($page));
    }

    private function grantAllRoutes(): void
    {
        $this->security->method('isGranted')->with(RouteVoter::READ_ROUTE, self::anything())->willReturn(true);
    }

    private function denyAllRoutes(): void
    {
        $this->security->method('isGranted')->willReturn(false);
    }

    /**
     * @param AbstractPage[] $pages
     * @param AbstractPage[] $pageData
     */
    private function expectChildren(array $pages, array $pageData): void
    {
        $this->pageRepository->method('findBy')->willReturn($pages);
        $this->pageDataRepository->method('findBy')->willReturn($pageData);
    }

    private function expectNoChildLookup(): void
    {
        $this->pageRepository->expects(self::never())->method('findBy');
        $this->pageDataRepository->expects(self::never())->method('findBy');
    }

    private function createRoute(): Route
    {
        return new Route();
    }

    private function createPage(?Route $route = null): Page
    {
        $page = new Page();
        $page->isTemplate = false;
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
