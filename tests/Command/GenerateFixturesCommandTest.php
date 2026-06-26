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

use Doctrine\Common\Collections\ArrayCollection;
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
use Symfony\Component\Console\Input\ArrayInput;
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

    private function makeRoute(string $path, string $name = null): Route
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
        $this->assertSame('silverback:api-components:generate-fixtures', GenerateFixturesCommand::getDefaultName());
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
        $this->assertStringContainsString("'CwaLayoutPrimary'", $content);
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
}
