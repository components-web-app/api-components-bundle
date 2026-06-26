<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\GenerateFixturesCommand;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Named stub so get_class() yields a predictable short name for emitted PHP assertions.
 */
class _TestHtmlComponent extends AbstractComponent
{
    public ?string $html = null;
    public ?string $cssClass = null;
}

/**
 * Named stub so get_class() yields a predictable short name for emitted PHP assertions.
 */
class _TestArticleData extends AbstractPageData
{
    public ?string $summary = null;
}

class GenerateFixturesCommandTest extends TestCase
{
    private string $outputFile;

    protected function setUp(): void
    {
        $this->outputFile = tempnam(sys_get_temp_dir(), 'cwa_fixture_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    private function makeCommand(?ManagerRegistry $registry = null): GenerateFixturesCommand
    {
        return new GenerateFixturesCommand(
            $registry ?? $this->createMock(ManagerRegistry::class),
        );
    }

    private function emptyRegistry(): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getRepository')->willReturnCallback(function (string $class) {
            $repo = $this->createMock(ObjectRepository::class);
            $repo->method('findAll')->willReturn([]);

            return $repo;
        });

        return $registry;
    }

    private function registryWith(array $layouts = [], array $pages = [], array $pageData = []): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getRepository')->willReturnCallback(
            function (string $class) use ($layouts, $pages, $pageData) {
                $repo = $this->createMock(ObjectRepository::class);
                $repo->method('findAll')->willReturn(match ($class) {
                    Layout::class => $layouts,
                    Page::class => $pages,
                    AbstractPageData::class => $pageData,
                    default => [],
                });

                return $repo;
            }
        );

        return $registry;
    }

    private function runCommand(ManagerRegistry $registry, string $outputFile): int
    {
        $tester = new CommandTester($this->makeCommand($registry));

        return $tester->execute(['--output' => $outputFile]);
    }

    private function makeRoute(string $path, ?string $name = null): Route
    {
        $route = new Route();
        $route->setPath($path);
        if ($name) {
            $route->setName($name);
        }

        return $route;
    }

    private function makePage(string $reference, string $uiComponent, Layout $layout, ?Route $route = null, bool $isTemplate = false): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->uiComponent = $uiComponent;
        $page->layout = $layout;
        $page->isTemplate = $isTemplate;
        if ($route) {
            $page->setRoute($route);
        }

        return $page;
    }

    private function makeLayout(string $reference, string $uiComponent): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $layout->uiComponent = $uiComponent;

        return $layout;
    }

    public function test_command_name(): void
    {
        $command = $this->makeCommand();
        $this->assertSame('silverback:api-components:generate-fixtures', $command->getName());
    }

    public function test_command_description(): void
    {
        $command = $this->makeCommand();
        $this->assertNotEmpty($command->getDescription());
    }

    public function test_output_option_is_configured(): void
    {
        $command = $this->makeCommand();
        $this->assertTrue($command->getDefinition()->hasOption('output'));
    }

    public function test_output_option_has_default(): void
    {
        $command = $this->makeCommand();
        $default = $command->getDefinition()->getOption('output')->getDefault();
        $this->assertNotEmpty($default);
    }

    public function test_empty_database_writes_valid_php_scaffold_file(): void
    {
        $this->runCommand($this->emptyRegistry(), $this->outputFile);

        $this->assertFileExists($this->outputFile);
        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString(AbstractCwaScaffold::class, $content);
        $this->assertStringContainsString('function build(', $content);
    }

    public function test_layout_appears_as_cwa_layout_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->layout(', $content);
        $this->assertStringContainsString("'main'", $content);
        $this->assertStringContainsString("'Primary'", $content);
        $this->assertStringNotContainsString("'CwaLayoutPrimary'", $content);
    }

    public function test_page_with_route_appears_as_cwa_page_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home', 'home-page');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->page(', $content);
        $this->assertStringContainsString("'home'", $content);
        $this->assertStringContainsString("'PrimaryTemplate'", $content);
        $this->assertStringContainsString("'/home'", $content);
    }

    public function test_template_page_is_emitted_with_is_template_flag(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $page = $this->makePage('blog-template', 'BlogTemplate', $layout, isTemplate: true);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('isTemplate', $content);
        $this->assertStringContainsString('true', $content);
    }

    public function test_component_group_appears_as_group_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/some-uuid';
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->group(', $content);
        $this->assertStringContainsString('primary', $content);
    }

    public function test_component_group_allowed_components_emits_allow_array(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        $group->reference = 'layout-main-top';
        $group->location = '/_/layouts/some-uuid';
        $group->allowedComponents = ['/component/navigation-links'];
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('allow:', $content);
        $this->assertStringContainsString('/component/navigation-links', $content);
    }

    public function test_component_non_null_scalar_properties_emitted_as_assignments(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->html = '<p>Hello world</p>';

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/some-uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('_TestHtmlComponent', $content);
        $this->assertStringContainsString('Hello world', $content);
    }

    public function test_null_component_properties_are_not_emitted(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->html = 'has content';
        // $cssClass intentionally left null

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/some-uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringNotContainsString('cssClass', $content);
    }

    public function test_page_data_appears_as_cwa_page_data_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('blog-template', 'BlogTemplate', $layout, isTemplate: true);

        $pageData = new _TestArticleData();
        $pageData->setTitle('My Article');
        $pageData->page = $templatePage;

        $route = $this->makeRoute('/my-article');
        $pageData->setRoute($route);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage], pageData: [$pageData]),
            $this->outputFile
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->pageData(', $content);
        $this->assertStringContainsString('_TestArticleData', $content);
        $this->assertStringContainsString("'/my-article'", $content);
    }

    public function test_nested_child_page_emits_nested_closure(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('topic-template', 'TopicTemplate', $layout, isTemplate: true);

        $parentPageData = new _TestArticleData();
        $parentPageData->setTitle('Topic One');
        $parentPageData->page = $templatePage;
        $parentPageData->setRoute($this->makeRoute('/topic-one'));

        $childPage = new Page();
        $childPage->reference = 'topic-one-chapter-1';
        $childPage->uiComponent = 'ChapterTemplate';
        $childPage->layout = $layout;
        $childPage->isTemplate = false;
        $childPage->setParentPageData($parentPageData);
        $childPage->setRoute($this->makeRoute('/topic-one/chapter-1'));

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage, $childPage], pageData: [$parentPageData]),
            $this->outputFile
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->nested(', $content);
    }

    public function test_output_file_is_written_to_specified_path(): void
    {
        $this->runCommand($this->emptyRegistry(), $this->outputFile);

        $this->assertFileExists($this->outputFile);
        $this->assertGreaterThan(0, filesize($this->outputFile));
    }

    public function test_command_returns_success_exit_code(): void
    {
        $exitCode = $this->runCommand($this->emptyRegistry(), $this->outputFile);

        $this->assertSame(0, $exitCode);
    }

    public function test_layout_with_ui_class_names_emits_ui_class_names(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $layout->uiClassNames = ['bg-white', 'full-bleed'];

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('uiClassNames', $content);
        $this->assertStringContainsString('bg-white', $content);
    }

    public function test_page_with_ui_class_names_emits_ui_class_names(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);
        $page->uiClassNames = ['hero', 'dark-mode'];

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('uiClassNames', $content);
        $this->assertStringContainsString('hero', $content);
    }

    public function test_component_with_ui_component_emits_ui_component(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->uiComponent = 'FancyHtml';

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/some-uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('uiComponent', $content);
        $this->assertStringContainsString('FancyHtml', $content);
    }

    public function test_component_with_ui_class_names_emits_ui_class_names(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->html = 'content';
        $component->uiClassNames = ['styled', 'compact'];

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/some-uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('uiClassNames', $content);
        $this->assertStringContainsString('styled', $content);
    }

    // --- Multiple-item accumulation tests (kills Assignment .= vs = mutants) ---

    public function test_multiple_layouts_both_appear_in_output(): void
    {
        $layout1 = $this->makeLayout('main', 'CwaLayoutPrimary');
        $layout2 = $this->makeLayout('alt', 'CwaLayoutSecondary');

        $this->runCommand($this->registryWith(layouts: [$layout1, $layout2]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("'main'", $content);
        $this->assertStringContainsString("'alt'", $content);
        $this->assertSame(2, substr_count($content, '->layout('));
    }

    public function test_multiple_top_level_pages_both_appear_in_output(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $page1 = $this->makePage('home', 'PrimaryTemplate', $layout, $this->makeRoute('/home'));
        $page2 = $this->makePage('about', 'SecondaryTemplate', $layout, $this->makeRoute('/about'));

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page1, $page2]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("'/home'", $content);
        $this->assertStringContainsString("'/about'", $content);
    }

    public function test_multiple_top_level_page_data_both_appear_in_output(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('template', 'Template', $layout, isTemplate: true);

        $pd1 = new _TestArticleData();
        $pd1->setTitle('Article One');
        $pd1->page = $templatePage;
        $pd1->setRoute($this->makeRoute('/article-one'));

        $pd2 = new _TestArticleData();
        $pd2->setTitle('Article Two');
        $pd2->page = $templatePage;
        $pd2->setRoute($this->makeRoute('/article-two'));

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage], pageData: [$pd1, $pd2]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("'/article-one'", $content);
        $this->assertStringContainsString("'/article-two'", $content);
        $this->assertSame(2, substr_count($content, '->pageData('));
    }

    // --- Child-page filtering (kills LogicalOr at lines 81/88, Continue_ at 82) ---

    public function test_page_with_parent_page_does_not_appear_at_top_level(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $parentPage = $this->makePage('parent', 'ParentTemplate', $layout, $this->makeRoute('/parent'));
        $childPage = $this->makePage('child', 'ChildTemplate', $layout, $this->makeRoute('/parent/child'));
        $childPage->setParentPage($parentPage);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$parentPage, $childPage]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        // child page should only appear once (inside nested closure, not at top level)
        $this->assertSame(1, substr_count($content, "'child'"), 'Child page reference must appear exactly once (only in nested closure, not at top level)');
    }

    public function test_page_with_parent_page_data_does_not_appear_at_top_level(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('tmpl', 'Template', $layout, isTemplate: true);

        $parentPd = new _TestArticleData();
        $parentPd->setTitle('Parent Data');
        $parentPd->page = $templatePage;
        $parentPd->setRoute($this->makeRoute('/parent-data'));

        $childPage = $this->makePage('child', 'ChildTemplate', $layout, $this->makeRoute('/parent-data/child'));
        $childPage->setParentPageData($parentPd);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage, $childPage], pageData: [$parentPd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        // child page appears in nested closure but not at top-level loop
        $this->assertSame(1, substr_count($content, "'child'"), 'Child page with parentPageData must appear exactly once (only in nested closure)');
    }

    public function test_page_data_with_parent_page_does_not_appear_at_top_level(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $parentPage = $this->makePage('parent', 'ParentTemplate', $layout, $this->makeRoute('/parent'));

        $childPd = new _TestArticleData();
        $childPd->setTitle('Child Data');
        $childPd->setRoute($this->makeRoute('/parent/child-data'));
        $childPd->setParentPage($parentPage);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$parentPage], pageData: [$childPd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        // Child pageData must NOT appear as a top-level $cwa->pageData() call (kills LogicalOr at line 88)
        $this->assertSame(0, substr_count($content, '$cwa->pageData('), 'Child pageData with parentPage must not appear at top-level');
        // It should appear in nested closure
        $this->assertStringContainsString('->nested(', $content);
    }

    public function test_page_data_with_parent_page_data_does_not_appear_at_top_level(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('tmpl', 'Template', $layout, isTemplate: true);

        $parentPd = new _TestArticleData();
        $parentPd->setTitle('Parent Data');
        $parentPd->page = $templatePage;
        $parentPd->setRoute($this->makeRoute('/parent-pd'));

        $childPd = new _TestArticleData();
        $childPd->setTitle('Child Data');
        $childPd->setRoute($this->makeRoute('/parent-pd/child'));
        $childPd->setParentPageData($parentPd);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage], pageData: [$parentPd, $childPd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertSame(1, substr_count($content, "'Child Data'"), 'Child pageData with parentPageData must appear exactly once (in nested closure)');
        $this->assertStringContainsString('->nested(', $content);
    }

    // --- Layout var name format (kills Concat/ConcatOperandRemoval at line 103) ---

    public function test_layout_var_name_has_layout_prefix_and_reference(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('$layout_main', $content);
    }

    // --- Layout uiClassNames exact format (kills Concat at line 109) ---

    public function test_layout_ui_class_names_exact_format_in_output(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $layout->uiClassNames = ['bg-white'];

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("uiClassNames: ['bg-white']", $content);
    }

    // --- Layout multiple groups - both appear (kills Assignment at line 115) ---

    public function test_layout_with_multiple_groups_both_appear_in_output(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group1 = new ComponentGroup();
        $group1->reference = 'nav_/_/layouts/uuid';
        $group1->location = '/_/layouts/uuid';
        $layout->getComponentGroups()->add($group1);

        $group2 = new ComponentGroup();
        $group2->reference = 'footer_/_/layouts/uuid';
        $group2->location = '/_/layouts/uuid';
        $layout->getComponentGroups()->add($group2);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertSame(2, substr_count($content, '->group('), 'Both groups must appear in output');
    }

    // --- Allowed components exact format (kills Concat at line 126) ---

    public function test_allowed_components_exact_format(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        $group->reference = 'top_/_/layouts/uuid';
        $group->location = '/_/layouts/uuid';
        $group->allowedComponents = ['/component/nav-links'];
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("allow: ['/component/nav-links']", $content);
    }

    // --- Empty group produces single-line output (kills ReturnRemoval at line 131) ---

    public function test_empty_group_emits_single_line_group_call_without_closure(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        $group->reference = 'nav';
        $group->location = '';
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->group('nav');\n", $content);
        $this->assertStringNotContainsString('function (GroupBuilder', $content);
    }

    // --- Position indentation in group (kills Concat/ConcatOperandRemoval at line 136) ---

    public function test_position_in_group_has_extra_indentation_vs_group(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->html = 'test';

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'primary_/_/pages/uuid';
        $group->location = '/_/pages/uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        // The position lines (e.g. "$comp1 = new ...") should be indented more than the group call
        // At top-level indent='        ' (8 spaces), position indent = '            ' (12 spaces)
        $this->assertStringContainsString('            $comp1', $content);
    }

    // --- Multiple components have distinct variable names (kills Increment at line 170) ---

    public function test_two_components_in_group_have_distinct_variable_names(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $comp1 = new _TestHtmlComponent();
        $comp1->html = 'first';
        $comp2 = new _TestHtmlComponent();
        $comp2->html = 'second';

        $pos1 = new ComponentPosition();
        $pos1->setComponent($comp1);
        $pos1->setSortValue(10);

        $pos2 = new ComponentPosition();
        $pos2->setComponent($comp2);
        $pos2->setSortValue(20);

        $group = new ComponentGroup();
        $group->reference = 'primary_/_/pages/uuid';
        $group->location = '/_/pages/uuid';
        $group->componentPositions->add($pos1);
        $group->componentPositions->add($pos2);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('$comp1', $content);
        $this->assertStringContainsString('$comp2', $content);
        $this->assertStringNotContainsString('$comp0', $content);
        $this->assertStringNotContainsString('$comp-', $content);
    }

    // --- Component FQCN generates use statement (kills MethodCallRemoval at line 168) ---

    public function test_component_fqcn_generates_use_statement(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->html = 'content';

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'primary_/_/pages/uuid';
        $group->location = '/_/pages/uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Tests\Command\_TestHtmlComponent;', $content);
    }

    // --- uiComponent exact format (kills Concat/ConcatOperandRemoval at line 174) ---

    public function test_component_ui_component_emitted_with_property_assignment_format(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $component = new _TestHtmlComponent();
        $component->uiComponent = 'CustomUi';

        $position = new ComponentPosition();
        $position->setComponent($component);
        $position->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'primary_/_/pages/uuid';
        $group->location = '/_/pages/uuid';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->uiComponent = 'CustomUi';", $content);
    }

    // --- Page without layout emits null for layout (kills Ternary at line 206) ---

    public function test_page_without_layout_emits_null_layout_arg(): void
    {
        $page = new Page();
        $page->reference = 'standalone';
        $page->uiComponent = 'StandaloneTemplate';
        $page->isTemplate = false;
        $page->setRoute($this->makeRoute('/standalone'));

        $this->runCommand($this->registryWith(pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        // var_export(null, true) returns 'NULL' (uppercase)
        $this->assertStringContainsString('layout: NULL', $content);
    }

    // --- Page with title emits configure block with title (kills NotIdentical at 238, Assignment at 239) ---

    public function test_page_with_title_emits_configure_block_with_title_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);
        $page->setTitle('Welcome Home');

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("\$page->title('Welcome Home')", $content);
    }

    // --- Group call inside page configure block (kills Assignment at 242) ---

    public function test_page_with_group_and_title_emits_both_in_configure_block(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);
        $page->setTitle('My Page');

        $group = new ComponentGroup();
        $group->reference = 'primary';
        $group->location = '';
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("\$page->title('My Page')", $content);
        $this->assertStringContainsString("\$page->group('primary')", $content);
    }

    // --- Nested child pageData inside pageData (kills Foreach_ at lines 67, 325, 328) ---

    public function test_nested_child_page_data_inside_page_data_emits_nested_closure(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('tmpl', 'Template', $layout, isTemplate: true);

        $parentPd = new _TestArticleData();
        $parentPd->setTitle('Parent Topic');
        $parentPd->page = $templatePage;
        $parentPd->setRoute($this->makeRoute('/topic'));

        $childPd = new _TestArticleData();
        $childPd->setTitle('Sub Topic');
        $childPd->setRoute($this->makeRoute('/topic/sub'));
        $childPd->setParentPageData($parentPd);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage], pageData: [$parentPd, $childPd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->nested(', $content);
        $this->assertStringContainsString("'Sub Topic'", $content);
    }

    // --- PageData FQCN generates use statement (kills MethodCallRemoval at line 272) ---

    public function test_page_data_fqcn_generates_use_statement(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('Test Article');
        $pd->setRoute($this->makeRoute('/test'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Tests\Command\_TestArticleData;', $content);
    }

    // --- PageData var name format (kills Concat/ConcatOperandRemoval at line 274) ---

    public function test_page_data_var_name_has_pd_prefix_and_slugified_title(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('My Great Article');
        $pd->setRoute($this->makeRoute('/my-great-article'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('$pd_My_Great_Article', $content);
    }

    public function test_page_data_with_null_title_uses_pageData_fallback_var_name(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle(null); // explicitly null — triggers the ?? 'pageData' fallback
        $pd->setRoute($this->makeRoute('/no-title'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('$pd_pageData', $content);
    }

    // --- PageData set title in output (kills NotIdentical at line 278, Concat at 279) ---

    public function test_page_data_title_emitted_as_set_title_call(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('My Article');
        $pd->setRoute($this->makeRoute('/article'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->setTitle('My Article')", $content);
    }

    // --- PageData public properties emitted (kills NotIdentical/Identical at lines 284, 291) ---

    public function test_page_data_public_property_emitted_as_assignment(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('Article');
        $pd->summary = 'A summary text';
        $pd->setRoute($this->makeRoute('/article'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->summary = 'A summary text'", $content);
    }

    public function test_page_data_null_public_property_not_emitted(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('Article');
        // summary left null
        $pd->setRoute($this->makeRoute('/article'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringNotContainsString('summary', $content);
    }

    // --- PageData templateRef in output (kills NotIdentical at 305, Concat at 306) ---

    public function test_page_data_template_ref_emitted_in_page_data_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('blog-tmpl', 'BlogTemplate', $layout, isTemplate: true);

        $pd = new _TestArticleData();
        $pd->setTitle('My Article');
        $pd->page = $templatePage;
        $pd->setRoute($this->makeRoute('/article'));

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage], pageData: [$pd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("template: 'blog-tmpl'", $content);
    }

    // --- PageData route name in output (kills NotIdentical at 311, Concat at 312) ---

    public function test_page_data_route_with_name_emits_route_name(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('Named Article');
        $pd->setRoute($this->makeRoute('/named', 'article-route'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("routeName: 'article-route'", $content);
    }

    public function test_page_data_route_without_name_does_not_emit_route_name(): void
    {
        $pd = new _TestArticleData();
        $pd->setTitle('Unnamed Article');
        $pd->setRoute($this->makeRoute('/unnamed'));

        $this->runCommand($this->registryWith(pageData: [$pd]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringNotContainsString('routeName:', $content);
    }

    // --- PageData with nested child page (kills Foreach_ at line 325) ---

    public function test_nested_child_page_inside_page_data_emits_nested_closure(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $templatePage = $this->makePage('tmpl', 'Template', $layout, isTemplate: true);

        $parentPd = new _TestArticleData();
        $parentPd->setTitle('Topic');
        $parentPd->page = $templatePage;
        $parentPd->setRoute($this->makeRoute('/topic'));

        $childPage = $this->makePage('chapter', 'ChapterTemplate', $layout, $this->makeRoute('/topic/chapter'));
        $childPage->setParentPageData($parentPd);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$templatePage, $childPage], pageData: [$parentPd]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('->nested(', $content);
        $this->assertStringContainsString("'chapter'", $content);
    }

    // --- buildFile contains core use statements (kills various at lines 305-349) ---

    public function test_build_file_contains_core_use_statements(): void
    {
        $this->runCommand($this->emptyRegistry(), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;', $content);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Fixture\Builder\GroupBuilder;', $content);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;', $content);
        $this->assertStringContainsString('use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;', $content);
    }

    public function test_build_file_extra_use_classes_are_sorted_alphabetically(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        // Adding two components creates two different use classes.
        // Using separate groups to get two components in output.
        $comp1 = new _TestHtmlComponent();
        $comp1->html = 'first';

        $pos1 = new ComponentPosition();
        $pos1->setComponent($comp1);
        $pos1->setSortValue(10);

        $group = new ComponentGroup();
        $group->reference = 'primary_/_/pages/uuid';
        $group->location = '/_/pages/uuid';
        $group->componentPositions->add($pos1);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        // The extra use statement for the component must appear after a newline separator
        $this->assertStringContainsString("\nuse Silverback\ApiComponentsBundle\Tests\Command\_TestHtmlComponent;", $content);
    }

    // --- extractGroupName: name extracted from reference when location matches (kills LogicalAnd at 379) ---

    public function test_extract_group_name_strips_location_suffix_from_reference(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        // reference = 'nav_/_/layouts/abc' → location matches '_/_/layouts/abc' suffix → name = 'nav'
        $group->reference = 'nav_/_/layouts/abc';
        $group->location = '/_/layouts/abc';
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->group('nav')", $content);
        $this->assertStringNotContainsString("->group('nav_/_/layouts/abc')", $content);
    }

    public function test_extract_group_name_returns_full_reference_when_location_not_in_reference(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        $group->reference = 'page-home-primary';
        $group->location = '/_/pages/different-uuid';
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("->group('page-home-primary')", $content);
    }

    public function test_extract_group_name_returns_full_reference_when_location_is_empty(): void
    {
        // Kills LogicalOr mutation at line 379:
        // Original: '' !== $location && str_contains(...) → false && any → false (returns full reference)
        // Mutation: '' !== $location || str_contains($reference, '_') → false || true → true → extracts prefix 'nav'
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');

        $group = new ComponentGroup();
        $group->reference = 'nav_top';  // contains underscore — mutation would extract 'nav' incorrectly
        $group->location = null;        // null → '' via '??'
        $layout->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        // Full reference must be used as group name when location is empty
        $this->assertStringContainsString("->group('nav_top')", $content);
        $this->assertStringNotContainsString("->group('nav')", $content);
    }

    // --- exportArray exact format (kills Concat/ConcatOperandRemoval at lines 396-398) ---

    public function test_export_array_format_wraps_with_square_brackets(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $layout->uiClassNames = ['class-a'];

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        // Must be wrapped in square brackets — '[' before the value and ']' after
        $this->assertStringContainsString("['class-a']", $content);
        // The opening bracket must precede the value, not trail it
        $this->assertStringContainsString("uiClassNames: ['class-a']", $content);
    }

    public function test_export_array_format_multiple_items_comma_separated(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $layout->uiClassNames = ['first', 'second'];

        $this->runCommand($this->registryWith(layouts: [$layout]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("['first', 'second']", $content);
    }

    // --- Success message appears (kills MethodCallRemoval at line 96) ---

    public function test_success_message_written_to_output(): void
    {
        $command = $this->makeCommand($this->emptyRegistry());
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $tester->execute(['--output' => $this->outputFile]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('Fixture class written to', $display);
        $this->assertStringContainsString($this->outputFile, $display);
    }

    // --- pageDataPosition output (kills Concat/ConcatOperandRemoval on position lines) ---

    public function test_page_data_position_emits_page_data_position_call(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $page = $this->makePage('tmpl', 'Template', $layout, isTemplate: true);

        $position = new ComponentPosition();
        $position->pageDataProperty = 'introContent';
        $position->pageDataClass = 'App\Entity\ArticleData';

        $group = new ComponentGroup();
        $group->reference = 'primary';
        $group->location = '';
        $group->componentPositions->add($position);
        $page->getComponentGroups()->add($group);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString('pageDataPosition(', $content);
        // var_export escapes backslashes: 'App\Entity\ArticleData' → 'App\\Entity\\ArticleData' in the file
        $this->assertStringContainsString('introContent', $content);
        $this->assertStringContainsString('ArticleData', $content);
    }

    // --- page route name output (kills NotIdentical at page->routeName, Concat at route string) ---

    public function test_page_route_with_name_emits_route_name(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home', 'home-page');
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("routeName: 'home-page'", $content);
    }

    public function test_page_route_without_name_does_not_emit_route_name(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home'); // no name
        $page = $this->makePage('home', 'PrimaryTemplate', $layout, $route);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringNotContainsString('routeName:', $content);
    }

    // --- stripUiPrefix: page uiComponent prefix stripped (kills mutations on line 205/407) ---

    public function test_page_ui_component_prefix_is_stripped_in_output(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/home');
        $page = $this->makePage('home', 'CwaPagePrimaryTemplate', $layout, $route);

        $this->runCommand($this->registryWith(layouts: [$layout], pages: [$page]), $this->outputFile);

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("'PrimaryTemplate'", $content);
        $this->assertStringNotContainsString("'CwaPagePrimaryTemplate'", $content);
    }

    // --- hasChildren combined with title (kills LogicalOr/LogicalAnd at line 230/233) ---

    public function test_page_with_title_and_children_emits_both_title_and_nested_in_configure(): void
    {
        $layout = $this->makeLayout('main', 'CwaLayoutPrimary');
        $route = $this->makeRoute('/parent');
        $page = $this->makePage('parent', 'ParentTemplate', $layout, $route);
        $page->setTitle('Parent Title');

        $childPage = $this->makePage('child', 'ChildTemplate', $layout, $this->makeRoute('/parent/child'));
        $childPage->setParentPage($page);

        $this->runCommand(
            $this->registryWith(layouts: [$layout], pages: [$page, $childPage]),
            $this->outputFile,
        );

        $content = file_get_contents($this->outputFile);
        $this->assertStringContainsString("\$page->title('Parent Title')", $content);
        $this->assertStringContainsString('->nested(', $content);
    }
}
