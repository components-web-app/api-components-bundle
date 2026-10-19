<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security\Voter;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Route\RouteReachabilityResolver;
use Silverback\ApiComponentsBundle\Helper\SubRequest\SubRequestKernel;
use Silverback\ApiComponentsBundle\Metadata\PageDataComponentMetadata;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ComponentVoterTest extends TestCase
{
    public function test_a_component_on_a_routed_page_is_granted_whichever_other_pages_it_is_also_on(): void
    {
        $routedPage = $this->page(new Route());
        $routelessPage = $this->page(null);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->component([$routedPage], [$routelessPage]), $routedPage));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->component([$routelessPage], [$routedPage]), $routedPage));
    }

    public function test_a_component_only_on_routeless_pages_is_denied(): void
    {
        $routedPage = $this->page(new Route());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->component([$this->page(null)], [$this->page(null)]), $routedPage));
    }

    public function test_a_component_in_a_cycle_held_in_the_database_reaches_the_page_above_it(): void
    {
        $routedPage = $this->page(new Route());
        $container = $this->component([$routedPage]);
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);
        $this->place($this->groupOwnedBy($child), $container);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($child, $routedPage));
    }

    public function test_a_cycle_held_in_the_database_with_no_page_ends_and_abstains_to_public(): void
    {
        $container = new DummyComponent();
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);
        $this->place($this->groupOwnedBy($child), $container);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($child, $this->page(new Route())));
    }

    public function test_a_cycle_held_in_the_database_on_routeless_pages_only_is_denied(): void
    {
        $container = $this->component([$this->page(null)]);
        $child = new DummyComponent();
        $this->place($this->groupOwnedBy($container), $child);
        $this->place($this->groupOwnedBy($child), $container);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($child, $this->page(new Route())));
    }

    public function test_a_component_in_a_layout_used_by_a_routed_page_is_granted(): void
    {
        $routedPage = $this->page(new Route());
        $layout = new Layout();
        $layout->pages = new ArrayCollection([$routedPage]);
        $group = new ComponentGroup();
        $group->layouts = new ArrayCollection([$layout]);
        $component = $this->component([$this->page(null)]);
        $this->place($group, $component);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($component, $routedPage));
    }

    public function test_a_group_reached_twice_does_not_stop_the_walk(): void
    {
        $routedPage = $this->page(new Route());
        $routelessGroup = new ComponentGroup();
        $routelessGroup->pages = new ArrayCollection([$this->page(null)]);
        $routedGroup = new ComponentGroup();
        $routedGroup->pages = new ArrayCollection([$routedPage]);
        $component = new DummyComponent();
        $this->place($routelessGroup, $component);
        $this->place($routelessGroup, $component);
        $this->place($routedGroup, $component);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($component, $routedPage));
    }

    public function test_a_vote_on_another_attribute_or_subject_abstains(): void
    {
        $voter = $this->voter($this->pageDataProvider());

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new DummyComponent(), [RouteVoter::READ_ROUTE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new \stdClass(), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_vote_outside_a_request_is_granted(): void
    {
        $voter = $this->voter($this->pageDataProvider(), requestStack: new RequestStack());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $this->component([$this->page(null)]), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_on_a_routeless_template_used_by_reachable_page_data_is_granted(): void
    {
        $voter = $this->voter($this->pageDataProvider(pageDataUsingItsPages: [new PageData()]), subRequestKernel: $this->reachableKernel());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $this->component([$this->page(null)]), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_on_a_routeless_template_used_only_by_unreachable_page_data_falls_through_to_the_routable_security(): void
    {
        $component = $this->component([$this->page(null)]);
        $provider = $this->pageDataProvider(pageDataUsingItsPages: [new PageData()]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter($provider, subRequestKernel: $this->unreachableKernel())->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter($provider, subRequestKernel: $this->unreachableKernel(), granted: true)->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_in_no_page_is_never_looked_up_by_page_template(): void
    {
        $pageDataProvider = $this->createMock(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);
        $pageDataProvider->expects(self::never())->method('findPageDataResourcesByPages');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter($pageDataProvider)->vote(new NullToken(), new DummyComponent(), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_held_by_reachable_page_data_is_granted(): void
    {
        $voter = $this->voter($this->pageDataProvider([new PageData()]), subRequestKernel: $this->reachableKernel());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), new DummyComponent(), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_held_only_by_unreachable_page_data_falls_through_to_the_routable_security(): void
    {
        $provider = $this->pageDataProvider([new PageData()]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter($provider, subRequestKernel: $this->unreachableKernel())->vote(new NullToken(), new DummyComponent(), [ComponentVoter::READ_COMPONENT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter($provider, subRequestKernel: $this->unreachableKernel(), granted: true)->vote(new NullToken(), new DummyComponent(), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_component_only_on_routeless_pages_is_granted_when_no_routable_security_is_configured(): void
    {
        $voter = $this->voter($this->pageDataProvider(), securityStr: null);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $this->component([$this->page(null)]), [ComponentVoter::READ_COMPONENT]));
    }

    public function test_a_draft_is_judged_by_the_pages_of_its_published_component(): void
    {
        $routedPage = $this->page(new Route());
        $published = new DummyPublishableComponent();
        $this->placeOnPages($published, [$routedPage]);
        $draft = new DummyPublishableComponent();
        $this->placeOnPages($draft, [$this->page(null)]);

        $voter = $this->voter($this->pageDataProvider(), $routedPage, registry: $this->registryReturning($published), isActivePublishedAt: false);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $draft, [ComponentVoter::READ_COMPONENT]));
    }

    public function test_an_active_publishable_component_is_judged_by_its_own_pages(): void
    {
        $routedPage = $this->page(new Route());
        $published = new DummyPublishableComponent();
        $this->placeOnPages($published, [$routedPage]);
        $component = new DummyPublishableComponent();
        $this->placeOnPages($component, [$this->page(null)]);

        $voter = $this->voter($this->pageDataProvider(), $routedPage, registry: $this->registryReturning($published));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]));
    }

    public function test_an_inactive_publishable_component_with_no_published_version_is_judged_by_its_own_pages(): void
    {
        $routedPage = $this->page(new Route());
        $component = new DummyPublishableComponent();
        $this->placeOnPages($component, [$routedPage]);

        $voter = $this->voter($this->pageDataProvider(), $routedPage, registry: $this->registryReturning(null), isActivePublishedAt: false);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]));
    }

    /**
     * @param list<Page> $pages
     */
    private function placeOnPages(AbstractComponent $component, array $pages): void
    {
        $group = new ComponentGroup();
        $group->pages = new ArrayCollection($pages);
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $component->addComponentPosition($position);
    }

    private function groupOwnedBy(DummyComponent $owner): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->addComponent($owner);

        return $group;
    }

    private function place(ComponentGroup $group, DummyComponent $component): void
    {
        $position = new ComponentPosition();
        $position->componentGroup = $group;
        $group->addComponentPosition($position);
        $component->addComponentPosition($position);
    }

    private function vote(AbstractComponent $component, Page $routedPage): int
    {
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn([]);
        $pageDataProvider->method('findPageDataResourcesByPages')->willReturn([]);

        return $this->voter($pageDataProvider, $routedPage)->vote(new NullToken(), $component, [ComponentVoter::READ_COMPONENT]);
    }

    private function voter(
        PageDataProvider $pageDataProvider,
        ?Page $routedPage = null,
        ?HttpKernelInterface $subRequestKernel = null,
        bool $granted = false,
        ?string $securityStr = "is_granted('ROLE_ADMIN')",
        ?ManagerRegistry $registry = null,
        bool $isActivePublishedAt = true,
        ?RequestStack $requestStack = null,
    ): ComponentVoter {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(static fn (string $attribute, mixed $subject) => null !== $routedPage && $subject === $routedPage->getRoute());
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn($granted);
        $publishableStatusChecker = $this->createStub(PublishableStatusChecker::class);
        $publishableStatusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $publishableStatusChecker->method('isActivePublishedAt')->willReturn($isActivePublishedAt);
        if (null === $requestStack) {
            $requestStack = new RequestStack();
            $requestStack->push(Request::create('/component/dummy_components/1'));
        }
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/page_data/page_datas/1');

        return new ComponentVoter(
            $pageDataProvider,
            $iriConverter,
            new SubRequestKernel($subRequestKernel ?? $this->createStub(HttpKernelInterface::class)),
            $requestStack,
            $publishableStatusChecker,
            $registry ?? $this->createStub(ManagerRegistry::class),
            new RouteReachabilityResolver($this->createStub(ManagerRegistry::class), $security),
            $accessChecker,
            $securityStr,
        );
    }

    private function reachableKernel(): HttpKernelInterface
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response());

        return $kernel;
    }

    private function unreachableKernel(): HttpKernelInterface
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willThrowException(new AccessDeniedHttpException());

        return $kernel;
    }

    /**
     * @param list<PageData> $pageDataHoldingTheComponent
     * @param list<PageData> $pageDataUsingItsPages
     */
    private function pageDataProvider(array $pageDataHoldingTheComponent = [], array $pageDataUsingItsPages = []): PageDataProvider
    {
        $pageDataProvider = $this->createStub(PageDataProvider::class);
        $pageDataProvider->method('findPageDataComponentMetadata')->willReturn($pageDataHoldingTheComponent ? [new PageDataComponentMetadata($pageDataHoldingTheComponent, new ArrayCollection(['component']))] : []);
        $pageDataProvider->method('findPageDataResourcesByPages')->willReturn($pageDataUsingItsPages);

        return $pageDataProvider;
    }

    private function registryReturning(?object $publishedResource): ManagerRegistry
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $object, string $field) => 'publishedResource' === $field ? $publishedResource : null);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        return $registry;
    }

    /**
     * @param list<Page> ...$pagesPerGroup
     */
    private function component(array ...$pagesPerGroup): DummyComponent
    {
        $component = new DummyComponent();
        foreach ($pagesPerGroup as $pages) {
            $group = new ComponentGroup();
            $group->pages = new ArrayCollection($pages);
            $position = new ComponentPosition();
            $position->componentGroup = $group;
            $component->addComponentPosition($position);
        }

        return $component;
    }

    private function page(?Route $route): Page
    {
        $page = new Page();
        if ($route) {
            $page->setRoute($route);
        }

        return $page;
    }
}
