<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Code;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ScaffoldRenderer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\Assignment;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ComponentPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\GroupPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ScaffoldPlan;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class ScaffoldRendererTest extends TestCase
{
    public function test_an_empty_plan_has_an_empty_build_method(): void
    {
        self::assertSame(<<<'PHP'
            <?php

            namespace App\DataFixtures;

            use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
            use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;

            class SiteScaffold extends AbstractCwaScaffold
            {
                public function build(CwaFixtureBuilder $cwa): void
                {
                }
            }

            PHP, $this->render([]));
    }

    public function test_a_page_with_no_group_is_one_statement(): void
    {
        $page = $this->page('homePage');
        $page->calls = ["title('Home')"];

        self::assertStringContainsString("        \$cwa->page('home', 'Primary', layout: 'main')\n            ->title('Home');\n", $this->render([$page]));
    }

    public function test_a_page_with_one_group_and_no_children_is_one_chain(): void
    {
        $page = $this->page('homePage');
        $group = new GroupPlan('primary', ["'primary'"]);
        $group->items[] = $this->inline();
        $page->groups[] = $group;

        self::assertStringContainsString("        \$cwa->page('home', 'Primary', layout: 'main')\n            ->group('primary')\n            ->add(new DummyComponent());\n", $this->render([$page]));
    }

    public function test_a_page_with_two_groups_is_held_in_a_variable_with_a_block_per_group(): void
    {
        $page = $this->page('homePage');
        $page->calls = ["title('Home')"];
        $page->groups = [new GroupPlan('hero', ["'hero'"]), new GroupPlan('primary', ["'primary'", 'allow: [DummyComponent::class]'])];

        self::assertStringContainsString(<<<'PHP'
                    $page = $cwa->page('home', 'Primary', layout: 'main')
                        ->title('Home');

                    $page->group('hero');

                    $page->group('primary', allow: [DummyComponent::class]);
                }
            PHP, $this->render([$page]));
    }

    public function test_a_page_held_in_a_variable_counts_the_assignment_towards_the_line_length(): void
    {
        $arguments = ["'home'", "'Primary'", "layout: 'main'", "route: '/" . str_repeat('a', 54) . "'"];
        $chained = $this->page('homePage');
        $chained->arguments = $arguments;
        $held = $this->page('homePage');
        $held->arguments = $arguments;
        $held->groups = [new GroupPlan('hero', ["'hero'"]), new GroupPlan('primary', ["'primary'"])];

        self::assertStringContainsString("        \$cwa->page('home', 'Primary', layout: 'main', route: '/" . str_repeat('a', 54) . "');\n", $this->render([$chained]));
        self::assertStringContainsString("        \$page = \$cwa->page(\n            'home',\n            'Primary',\n", $this->render([$held]));
    }

    public function test_a_single_child_is_nested_as_a_first_class_callable(): void
    {
        $page = $this->page('aboutPage');
        $page->children[] = $this->page('teamPage');

        $code = $this->render([$page]);

        self::assertStringContainsString("        \$cwa->page('home', 'Primary', layout: 'main')\n            ->nested(\$this->teamPage(...));\n", $code);
        self::assertStringContainsString("    private function teamPage(CwaFixtureBuilder \$cwa): void\n", $code);
        self::assertStringNotContainsString('$this->teamPage($cwa);', $code);
    }

    public function test_each_child_is_nested_by_its_own_call(): void
    {
        $page = $this->page('aboutPage');
        $page->groups[] = new GroupPlan('primary', ["'primary'"]);
        $page->children = [$this->page('teamPage'), $this->page('historyPage')];

        self::assertStringContainsString(<<<'PHP'
                    $page->group('primary');

                    $page->nested($this->teamPage(...));
                    $page->nested($this->historyPage(...));
                }
            PHP, $this->render([$page]));
    }

    public function test_children_of_a_page_with_no_group_are_chained(): void
    {
        $page = $this->page('aboutPage');
        $page->children = [$this->page('teamPage'), $this->page('historyPage')];

        self::assertStringContainsString("        \$cwa->page('home', 'Primary', layout: 'main')\n            ->nested(\$this->teamPage(...))\n            ->nested(\$this->historyPage(...));\n", $this->render([$page]));
    }

    public function test_a_component_block_declares_sets_publishes_links_and_owns_groups_in_that_order(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $component = $this->declared('heroDummyComponent', $layout);
        $component->assignments[] = new Assignment('uiComponent', "'Hero'");
        $component->assignments[] = new Assignment('internalNote', "'check'", 'setInternalNote');
        $component->publishNow = true;
        $component->links[] = "linkRoute('route', routeName: 'home')";
        $owned = new GroupPlan('panels', ["'panels'"]);
        $owned->items[] = $this->inline();
        $component->groups[] = $owned;
        $layout->components[] = $component;

        self::assertStringContainsString(<<<'PHP'
                    $heroDummyComponent = new DummyComponent();
                    $heroDummyComponent->uiComponent = 'Hero';
                    $heroDummyComponent->setInternalNote('check');
                    $cwa->publish($heroDummyComponent);
                    $cwa->component($heroDummyComponent)->linkRoute('route', routeName: 'home');
                    $cwa->component($heroDummyComponent)
                        ->group('panels')
                        ->add(new DummyComponent());

                    $cwa->layout('main', 'Primary');
            PHP, $this->render([$layout]));
    }

    public function test_several_links_are_one_chain(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $component = $this->declared('linkDummyComponent', $layout);
        $component->links = ["linkRoute('route', routeName: 'home')", "linkRoute('backRoute', routeName: 'about')"];
        $layout->components[] = $component;

        self::assertStringContainsString("        \$cwa->component(\$linkDummyComponent)\n            ->linkRoute('route', routeName: 'home')\n            ->linkRoute('backRoute', routeName: 'about');\n", $this->render([$layout]));
    }

    public function test_a_scheduled_publish_gives_its_date(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $component = $this->declared('soonDummyComponent', $layout);
        $component->publishAt = "new \\DateTimeImmutable('2099-01-15T09:00:00+00:00')";
        $layout->components[] = $component;

        self::assertStringContainsString("        \$cwa->publish(\$soonDummyComponent, at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'));\n", $this->render([$layout]));
    }

    public function test_a_draft_is_its_own_block_after_its_published_component(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $published = $this->declared('liveDummyComponent', $layout);
        $draft = $this->declared('liveDummyComponentDraft', $layout);
        $draft->assignments[] = new Assignment('uiComponent', "'Next'");
        $published->draft = $draft;
        $layout->components[] = $published;

        self::assertStringContainsString(<<<'PHP'
                    $liveDummyComponent = new DummyComponent();

                    $liveDummyComponentDraft = new DummyComponent();
                    $liveDummyComponentDraft->uiComponent = 'Next';
                    $cwa->component($liveDummyComponent)->draft($liveDummyComponentDraft);

                    $cwa->layout('main', 'Primary');
            PHP, $this->render([$layout]));
    }

    public function test_a_property_is_declared_on_the_class_and_written_through_this(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $shared = $this->declared('sharedDummyComponent', $layout);
        $shared->property = true;
        $layout->components[] = $shared;

        $code = $this->render([$layout], [$shared]);

        self::assertStringContainsString("{\n    private DummyComponent \$sharedDummyComponent;\n\n    public function build(", $code);
        self::assertStringContainsString("        \$this->sharedDummyComponent = new DummyComponent();\n", $code);
    }

    public function test_late_assignments_follow_every_declaration(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'mainLayout');
        $layout->arguments = ["'main'", "'Primary'"];
        $card = $this->declared('cardDummyComponent', $layout);
        $teaser = $this->declared('teaserDummyComponent', $layout);
        $layout->components = [$card, $teaser];
        $layout->lateAssignments[] = new Assignment('related', $teaser, null, $card);

        self::assertStringContainsString("        \$teaserDummyComponent = new DummyComponent();\n\n        \$cardDummyComponent->related = \$teaserDummyComponent;\n\n        \$cwa->layout(", $this->render([$layout]));
    }

    public function test_a_component_expression_is_new_inline_published_inline_a_variable_or_a_property(): void
    {
        $plan = $this->inline();
        self::assertSame('new DummyComponent()', ScaffoldRenderer::expression($plan));
        $plan->publishNow = true;
        self::assertSame('$cwa->publish(new DummyComponent())', ScaffoldRenderer::expression($plan));

        $declared = $this->declared('heroDummyComponent', new MethodPlan(MethodKind::Layout, 'mainLayout'));
        self::assertSame('$heroDummyComponent', ScaffoldRenderer::expression($declared));
        $declared->property = true;
        self::assertSame('$this->heroDummyComponent', ScaffoldRenderer::expression($declared));
    }

    public function test_page_data_is_built_then_registered_with_its_calls(): void
    {
        $method = new MethodPlan(MethodKind::PageData, 'eventPageData');
        $method->entityClass = 'PageData';
        $method->arguments = ['$pageData', "route: '/event'"];
        $method->entityAssignments[] = new Assignment('title', "'Event'", 'setTitle');
        $method->entityAssignments[] = new Assignment('component', $this->inline());
        $method->calls = ["linkRoute('relatedRoute', routeName: 'about')"];

        self::assertStringContainsString(<<<'PHP'
                private function eventPageData(CwaFixtureBuilder $cwa): void
                {
                    $pageData = new PageData();
                    $pageData->setTitle('Event');
                    $pageData->component = new DummyComponent();

                    $cwa->pageData($pageData, route: '/event')
                        ->linkRoute('relatedRoute', routeName: 'about');
                }
            PHP, $this->render([$method]));
    }

    public function test_redirects_are_one_statement_each(): void
    {
        $method = new MethodPlan(MethodKind::Redirects, 'redirects');
        $method->redirects = [["'/old'", "to: 'about'"], ["'/older'", "to: 'old'", "name: 'legacy'"]];

        self::assertStringContainsString("    {\n        \$cwa->redirect('/old', to: 'about');\n        \$cwa->redirect('/older', to: 'old', name: 'legacy');\n    }\n", $this->render([$method]));
    }

    /**
     * @param list<MethodPlan>    $methods
     * @param list<ComponentPlan> $properties
     */
    private function render(array $methods, array $properties = []): string
    {
        return (new ScaffoldRenderer())->render(new ScaffoldPlan($methods, $properties, new ImportCollector('SiteScaffold')), 'App\\DataFixtures', 'SiteScaffold');
    }

    private function page(string $name): MethodPlan
    {
        $page = new MethodPlan(MethodKind::Page, $name);
        $page->arguments = ["'home'", "'Primary'", "layout: 'main'"];

        return $page;
    }

    private function declared(string $name, MethodPlan $scope): ComponentPlan
    {
        $plan = new ComponentPlan(new DummyComponent(), 'DummyComponent', $scope);
        $plan->name = $name;

        return $plan;
    }

    private function inline(): ComponentPlan
    {
        $plan = new ComponentPlan(new DummyComponent(), 'DummyComponent', new MethodPlan(MethodKind::Layout, 'unused'));
        $plan->inline = true;

        return $plan;
    }
}
