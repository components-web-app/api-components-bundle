<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache;

use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\HttpCache\ManifestKeyResolver;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class ManifestKeyResolverTest extends TestCase
{
    private int $iriConversions = 0;
    private ManifestKeyResolver $resolver;

    protected function setUp(): void
    {
        $this->iriConversions = 0;

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter
            ->method('getIriFromResource')
            ->willReturnCallback(function (object $resource): string {
                ++$this->iriConversions;

                return $this->iri($resource);
            });

        $this->resolver = new ManifestKeyResolver($iriConverter);
    }

    public function test_a_page_owns_its_own_manifest(): void
    {
        $page = $this->createPage();

        self::assertSame([$this->iri($page)], $this->resolver->resolve($page));
    }

    public function test_page_data_owns_its_own_manifest(): void
    {
        $pageData = $this->createPageData();

        self::assertSame([$this->iri($pageData)], $this->resolver->resolve($pageData));
    }

    public function test_a_route_resolves_to_the_page_it_publishes(): void
    {
        $page = $this->createPage();
        $route = new Route();
        $route->setPage($page);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($route));
    }

    public function test_a_route_resolves_to_the_page_data_it_publishes(): void
    {
        $pageData = $this->createPageData();
        $route = new Route();
        $route->setPageData($pageData);

        self::assertSame([$this->iri($pageData)], $this->resolver->resolve($route));
    }

    public function test_a_route_with_nothing_published_resolves_to_no_keys(): void
    {
        self::assertSame([], $this->resolver->resolve(new Route()));
    }

    public function test_a_layout_resolves_to_every_page_that_uses_it(): void
    {
        $layout = new Layout();
        $layout->pages->add($first = $this->createPage());
        $layout->pages->add($second = $this->createPage());

        self::assertSame([$this->iri($first), $this->iri($second)], $this->resolver->resolve($layout));
    }

    public function test_a_component_group_resolves_to_the_pages_it_is_placed_in(): void
    {
        $group = $this->createComponentGroup();
        $group->pages->add($page = $this->createPage());

        self::assertSame([$this->iri($page)], $this->resolver->resolve($group));
    }

    public function test_a_component_group_resolves_through_a_layout_to_that_layouts_pages(): void
    {
        $layout = new Layout();
        $layout->pages->add($page = $this->createPage());

        $group = $this->createComponentGroup();
        $group->layouts->add($layout);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($group));
    }

    public function test_a_component_position_resolves_through_its_group(): void
    {
        $group = $this->createComponentGroup();
        $group->pages->add($page = $this->createPage());

        $position = new ComponentPosition();
        $position->componentGroup = $group;

        self::assertSame([$this->iri($page)], $this->resolver->resolve($position));
    }

    public function test_a_component_position_with_no_group_resolves_to_no_keys(): void
    {
        self::assertSame([], $this->resolver->resolve(new ComponentPosition()));
    }

    public function test_a_nested_component_group_resolves_up_through_its_parent_component(): void
    {
        $outerGroup = $this->createComponentGroup();
        $outerGroup->pages->add($page = $this->createPage());

        $parentComponent = new DummyComponent();
        $parentPosition = new ComponentPosition();
        $parentPosition->componentGroup = $outerGroup;
        $parentComponent->addComponentPosition($parentPosition);

        $innerGroup = $this->createComponentGroup();
        $innerGroup->components->add($parentComponent);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($innerGroup));
    }

    public function test_a_cycle_between_component_groups_does_not_recurse_forever(): void
    {
        $first = $this->createComponentGroup();
        $second = $this->createComponentGroup();
        $second->pages->add($page = $this->createPage());

        $firstComponent = new DummyComponent();
        $firstPosition = new ComponentPosition();
        $firstPosition->componentGroup = $first;
        $firstComponent->addComponentPosition($firstPosition);

        $secondComponent = new DummyComponent();
        $secondPosition = new ComponentPosition();
        $secondPosition->componentGroup = $second;
        $secondComponent->addComponentPosition($secondPosition);

        $first->components->add($secondComponent);
        $second->components->add($firstComponent);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($first));
    }

    public function test_a_revisited_group_does_not_abandon_the_rest_of_the_queue(): void
    {
        $first = $this->createComponentGroup();
        $third = $this->createComponentGroup();
        $third->pages->add($page = $this->createPage());

        $selfReferencingComponent = new DummyComponent();
        $selfReferencingPosition = new ComponentPosition();
        $selfReferencingPosition->componentGroup = $first;
        $selfReferencingComponent->addComponentPosition($selfReferencingPosition);

        $onwardComponent = new DummyComponent();
        $onwardPosition = new ComponentPosition();
        $onwardPosition->componentGroup = $third;
        $onwardComponent->addComponentPosition($onwardPosition);

        $first->components->add($selfReferencingComponent);
        $first->components->add($onwardComponent);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($first));
    }

    public function test_a_page_reached_by_two_paths_yields_one_key(): void
    {
        $page = $this->createPage();
        $group = $this->createComponentGroup();
        $group->pages->add($page);

        $layout = new Layout();
        $layout->pages->add($page);
        $group->layouts->add($layout);

        self::assertSame([$this->iri($page)], $this->resolver->resolve($group));
    }

    public function test_a_component_is_not_a_structural_resource_and_resolves_to_no_keys(): void
    {
        $component = new DummyComponent();
        $position = new ComponentPosition();
        $position->componentGroup = $this->createComponentGroup();
        $position->componentGroup->pages->add($this->createPage());
        $component->addComponentPosition($position);

        self::assertSame([], $this->resolver->resolve($component));
    }

    public function test_the_resolved_keys_are_memoised_for_the_request(): void
    {
        $page = $this->createPage();
        $group = $this->createComponentGroup();
        $group->pages->add($page);

        $this->resolver->resolve($group);
        $this->resolver->resolve($group);

        self::assertSame(1, $this->iriConversions);
    }

    private function iri(object $resource): string
    {
        return \sprintf('/%s/%s', strtolower((new \ReflectionClass($resource))->getShortName()), $resource->getId());
    }

    private function createComponentGroup(): ComponentGroup
    {
        $group = new ComponentGroup();
        $reflection = new \ReflectionProperty($group, 'id');
        $reflection->setValue($group, Uuid::uuid4());

        return $group;
    }

    private function createPage(): Page
    {
        $page = new Page();
        $page->isTemplate = false;

        return $this->withId($page);
    }

    private function createPageData(): PageData
    {
        return $this->withId(new PageData());
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
