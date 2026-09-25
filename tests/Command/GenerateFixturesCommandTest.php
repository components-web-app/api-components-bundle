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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\GenerateFixturesCommand;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PublishableListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\TimestampedListener;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\UploadableListener;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

class GenerateFixturesCommandTest extends TestCase
{
    private string $outputDirectory;
    private string $outputFile;
    private Filesystem $filesystem;
    private string $display = '';

    /** @var array<string, list<object>> */
    private array $repositories = [];

    /** @var list<string> */
    private array $blankNodeClasses = [];

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir() . '/cwa-generate-fixtures-test-' . bin2hex(random_bytes(6));
        mkdir($this->outputDirectory);
        $this->outputFile = $this->outputDirectory . '/GeneratedScaffold.php';
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->outputDirectory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->outputDirectory);
    }

    public function test_an_empty_database_generates_a_valid_scaffold(): void
    {
        $code = $this->generate();

        self::assertStringContainsString('class GeneratedScaffold extends AbstractCwaScaffold', $code);
        self::assertStringContainsString('namespace App\\DataFixtures;', $code);
        self::assertStringContainsString('$c = [];', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringContainsString('Fixture class written to ' . $this->outputFile, $this->display);
    }

    public function test_a_group_with_allowed_components_and_positions_uses_named_arguments_and_class_names(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/dummy_components'];
        $this->position($group, new DummyComponent(), 10);

        $code = $this->generate();

        self::assertStringContainsString("\$layout = \$cwa->layout('main', 'Primary');", $code);
        self::assertStringContainsString("\$g[1] = \$layout->group('top', allow: [DummyComponent::class]);", $code);
        self::assertStringContainsString('$c[1] = new DummyComponent();', $code);
        self::assertStringContainsString('$g[1]->add($c[1]);', $code);
        self::assertStringContainsString('use Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\DummyComponent;', $code);
    }

    public function test_an_allowed_iri_that_matches_no_component_class_is_reported_and_left_out(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/removed_components'];

        $code = $this->generate();

        self::assertStringContainsString("\$g[1] = \$layout->group('top');", $code);
        self::assertStringContainsString('Allowed component "/component/removed_components" on the component group "top_/_/layouts/main" does not match a component class.', $this->display);
    }

    public function test_a_page_is_emitted_with_its_fields_route_and_allowed_group(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $page->setTitle('Home');
        $page->setMetaDescription('Welcome');
        $page->uiClassNames = ['wide'];
        $group = $this->group('primary_/_/pages/home', '/_/pages/home', $page);
        $group->allowedComponents = ['/component/dummy_components'];

        $code = $this->generate();

        self::assertStringContainsString("\$page = \$cwa->page('home', 'Primary', layout: 'main', route: '/', routeName: 'home', uiClassNames: ['wide']);", $code);
        self::assertStringContainsString("\$page->title('Home');", $code);
        self::assertStringContainsString("\$page->metaDescription('Welcome');", $code);
        self::assertStringContainsString("\$g[1] = \$page->group('primary', allow: [DummyComponent::class]);", $code);
        self::assertStringNotContainsString('->liveAt(', $code);
    }

    public function test_a_template_page_without_a_layout_is_emitted_with_an_empty_layout_reference(): void
    {
        $page = $this->page('template', null);
        $page->isTemplate = true;

        $code = $this->generate();

        self::assertStringContainsString("\$page = \$cwa->page('template', 'Primary', layout: '', isTemplate: true);", $code);
    }

    public function test_a_scheduled_or_draft_route_is_emitted_and_a_live_one_is_not(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $scheduled = $this->route('/soon', 'soon');
        $scheduled->setLiveAt(new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));
        $this->page('soon', $layout, $scheduled);
        $draft = $this->route('/draft', 'draft');
        $draft->setLiveAt(null);
        $this->page('draft', $layout, $draft);
        $live = $this->route('/live', 'live');
        $live->setLiveAt(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'));
        $this->page('live', $layout, $live);

        $code = $this->generate();

        self::assertStringContainsString("\$page->liveAt(new \\DateTimeImmutable('2999-01-01T00:00:00.000000+00:00'));", $code);
        self::assertStringContainsString('$page->liveAt(NULL);', $code);
        self::assertSame(2, substr_count($code, '->liveAt('));
    }

    public function test_a_group_shared_through_a_location_reference_is_emitted_for_each_owner_with_its_positions_once(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $group = $this->group('nav_site', '/_/layouts/main', $layout);
        $page->getComponentGroups()->add($group);
        $group->pages->add($page);
        $this->position($group, new DummyComponent(), 10);

        $code = $this->generate();

        self::assertStringContainsString("\$g[1] = \$layout->group('nav', locationReference: 'site');", $code);
        self::assertStringContainsString("\$g[2] = \$page->group('nav', locationReference: 'site');", $code);
        self::assertSame(1, substr_count($code, '->add('));
    }

    public function test_a_group_used_by_another_owner_without_a_location_reference_is_reported_for_that_owner(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $page->getComponentGroups()->add($group);
        $group->pages->add($page);

        $code = $this->generate();

        self::assertStringContainsString("\$layout->group('top');", $code);
        self::assertStringNotContainsString("\$page->group('top'", $code);
        self::assertStringContainsString('Component group "top_/_/layouts/main" is also used by /_/pages/home, which the fixture builder cannot share it with.', $this->display);
    }

    public function test_a_group_reference_without_a_name_separator_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $this->group('orphan', '/_/layouts/other', $layout);

        $code = $this->generate();

        self::assertStringNotContainsString('->group(', $code);
        self::assertStringContainsString('Component group "orphan" on /_/layouts/main has a reference the fixture builder cannot express.', $this->display);
    }

    public function test_positions_are_emitted_in_sort_order_and_a_component_in_two_positions_is_constructed_once(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $second = new DummyComponent();
        $second->uiComponent = 'Second';
        $first = new DummyComponent();
        $first->uiComponent = 'First';
        $this->position($group, $second, 20);
        $this->position($group, $first, 10);
        $this->position($group, $first, 30);

        $code = $this->generate();

        self::assertLessThan(strpos($code, "'Second'"), strpos($code, "'First'"));
        self::assertSame(2, substr_count($code, 'new DummyComponent()'));
        self::assertSame(2, substr_count($code, '$g[1]->add($c[1]);'));
    }

    public function test_a_position_without_a_component_is_skipped(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $position = new ComponentPosition();
        $position->sortValue = 10;
        $position->componentGroup = $group;
        $group->componentPositions->add($position);

        $code = $this->generate();

        self::assertStringNotContainsString('->add(', $code);
    }

    public function test_a_page_data_position_is_emitted_and_its_fallback_component_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $position = $this->position($group, new DummyComponent(), 10);
        $position->pageDataClass = PageDataWithComponent::class;
        $position->pageDataProperty = 'component';

        $code = $this->generate();

        self::assertStringContainsString("\$g[1]->pageDataPosition(PageDataWithComponent::class, 'component');", $code);
        self::assertStringNotContainsString('new DummyComponent()', $code);
        self::assertStringContainsString('The fallback component of the page data position "component" in the component group "top_/_/layouts/main".', $this->display);
    }

    public function test_a_component_keeps_inherited_public_non_public_and_date_fields_and_skips_defaults(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->label = 'About';
        $link->theme = 'dark';
        $link->eventDate = new \DateTimeImmutable('2027-01-01T10:00:00+00:00');
        $link->setInternalNote('check');
        $link->uiClassNames = ['a', 'b'];
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$c[1]->label = 'About';", $code);
        self::assertStringContainsString("\$c[1]->theme = 'dark';", $code);
        self::assertStringContainsString("\$c[1]->eventDate = new \\DateTimeImmutable('2027-01-01T10:00:00.000000+00:00');", $code);
        self::assertStringContainsString("\$c[1]->setInternalNote('check');", $code);
        self::assertStringContainsString("\$c[1]->uiClassNames = [0 => 'a', 1 => 'b'];", $code);
        self::assertStringNotContainsString('uiComponent', $code);
        self::assertStringNotContainsString('createdAt', $code);
    }

    public function test_a_value_that_cannot_be_written_as_php_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $component = new DummyComponent();
        $component->uiClassNames = [new \stdClass()];
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringNotContainsString('uiClassNames', $code);
        self::assertStringContainsString('The value of ' . DummyComponent::class . '::$uiClassNames cannot be written as PHP.', $this->display);
    }

    public function test_a_route_reference_is_set_once_routes_exist(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $route = $this->route('/about', 'about');
        $this->page('about', $layout, $route);
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->route = $route;
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->afterRoutes(function (CwaFixtureBuilder \$cwa) use (&\$c): void {\n            \$c[1]->route = \$cwa->getRoute('about');\n        });", $code);
    }

    public function test_a_reference_to_a_route_outside_the_site_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->route = $this->route('/elsewhere', 'elsewhere');
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringContainsString(DummyNavigationLink::class . '::$route refers to', $this->display);
    }

    public function test_a_draft_is_created_after_routes_linked_to_its_published_component_and_persisted(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $published = new DummyPublishableComponent();
        $published->reference = 'live';
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $draft = new DummyPublishableComponent();
        $draft->reference = 'live-draft';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $this->position($group, $published, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$c[1]->reference = 'live';", $code);
        self::assertStringContainsString("\$c[1]->setPublishedAt(new \\DateTime('2020-01-01T00:00:00.000000+00:00'));", $code);
        self::assertStringContainsString('            $c[2] = new DummyPublishableComponent();', $code);
        self::assertStringContainsString("            \$c[2]->reference = 'live-draft';", $code);
        self::assertStringContainsString('            $c[2]->setPublishedResource($c[1]);', $code);
        self::assertStringContainsString('            $cwa->persist($c[2]);', $code);
        self::assertLessThan(strpos($code, '$cwa->persist($c[2]);'), strpos($code, '$c[2]->setPublishedResource($c[1]);'));
    }

    public function test_a_stored_file_is_exported_without_its_token_and_names_never_collide(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/image-0a1b2c3d.png', 'first');
        $this->filesystem->write('components/image-9f8e7d6c.png', 'second');
        foreach (['components/image-0a1b2c3d.png', 'components/image-9f8e7d6c.png'] as $sort => $path) {
            $component = new DummyUploadableAndPublishable();
            $component->setFilename($path);
            $this->position($group, $component, $sort);
        }

        $code = $this->generate();

        self::assertStringContainsString("\$c[1]->file = new File(__DIR__ . '/assets/image.png');", $code);
        self::assertStringContainsString("\$c[2]->file = new File(__DIR__ . '/assets/image-2.png');", $code);
        self::assertStringContainsString('use Symfony\\Component\\HttpFoundation\\File\\File;', $code);
        self::assertStringNotContainsString('filename', $code);
        self::assertSame('first', file_get_contents($this->outputDirectory . '/assets/image.png'));
        self::assertSame('second', file_get_contents($this->outputDirectory . '/assets/image-2.png'));
        self::assertStringContainsString('2 stored file(s) exported to ' . $this->outputDirectory . '/assets', $this->display);
    }

    public function test_a_stored_file_that_cannot_be_read_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $component = new DummyUploadableAndPublishable();
        $component->setFilename('components/missing.png');
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringNotContainsString('new File(', $code);
        self::assertStringContainsString('The stored file "components/missing.png" could not be read', $this->display);
        self::assertDirectoryDoesNotExist($this->outputDirectory . '/assets');
    }

    public function test_a_component_owned_group_is_emitted_through_the_component_builder(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $tabs = new DummyComponent();
        $this->position($group, $tabs, 10);
        $iri = '/component/dummy_components/' . spl_object_id($tabs);
        $panels = new ComponentGroup();
        $panels->reference = 'panels_' . $iri;
        $panels->location = $iri;
        $tabs->addComponentGroup($panels);
        $panels->components->add($tabs);
        $this->position($panels, new DummyComponent(), 10);

        $code = $this->generate();

        self::assertStringContainsString("\$g[2] = \$cwa->component(\$c[1])->group('panels');", $code);
        self::assertStringContainsString('$g[2]->add($c[2]);', $code);
    }

    public function test_page_data_is_emitted_with_its_fields_and_component_property_inline(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $template = $this->page('template', $layout);
        $template->isTemplate = true;
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('Conference');
        $pageData->setMetaDescription('An event');
        $pageData->page = $template;
        $intro = new DummyComponent();
        $intro->uiComponent = 'Intro';
        $pageData->component = $intro;
        $pageData->setRoute($this->route('/conference', 'conference'));
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString('$pageData = new PageDataWithComponent();', $code);
        self::assertStringContainsString("\$pageData->setTitle('Conference');", $code);
        self::assertStringContainsString("\$pageData->setMetaDescription('An event');", $code);
        self::assertStringContainsString("\$c[1]->uiComponent = 'Intro';", $code);
        self::assertStringContainsString('$pageData->component = $c[1];', $code);
        self::assertStringContainsString("\$cwa->pageData(\$pageData, template: 'template', route: '/conference', routeName: 'conference');", $code);
    }

    public function test_children_are_emitted_inside_nested_closures(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $child = $this->page('child', $layout, $this->route('/parent/child', 'child'));
        $child->setParentPage($parent);
        $pageData = new PageData();
        $pageData->setTitle('Child data');
        $pageData->setParentPage($parent);
        $pageData->setRoute($this->route('/parent/data', 'data'));
        $pageData->getRoute()->setLiveAt(null);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString("\$page->nested(function (CwaFixtureBuilder \$child) use (\$cwa, &\$c, &\$g): void {\n            \$page = \$child->page('child', 'Primary', layout: 'main', route: '/parent/child', routeName: 'child');", $code);
        self::assertStringContainsString("            \$child->pageData(\$pageData, route: '/parent/data', routeName: 'data')\n                ->liveAt(NULL);", $code);
        self::assertSame(1, substr_count($code, "\$cwa->page('parent'"));
    }

    public function test_redirects_are_emitted_after_their_targets_and_an_orphan_redirect_is_reported(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $this->page('about', $layout, $about);
        $older = $this->route('/older', 'older');
        $old = $this->route('/old', 'old');
        $older->setRedirect($old);
        $old->setRedirect($about);
        $orphan = $this->route('/orphan', 'orphan');
        $orphan->setRedirect($this->route('/nowhere', 'nowhere'));
        $this->repositories[Route::class] = [$older, $old, $about, $orphan];

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->redirect('/old', to: 'about', name: 'old');\n        \$cwa->redirect('/older', to: 'old', name: 'older');", $code);
        self::assertStringContainsString('The redirect from "/orphan" to "/nowhere", whose target has no page.', $this->display);
    }

    public function test_a_complete_site_generates_valid_php(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $route = $this->route('/', 'home');
        $page = $this->page('home', $layout, $route);
        $group = $this->group('primary_/_/pages/home', '/_/pages/home', $page);
        $group->allowedComponents = ['/component/dummy_navigation_links'];
        $link = new DummyNavigationLink();
        $link->route = $route;
        $link->eventDate = new \DateTimeImmutable('2027-01-01T10:00:00+00:00');
        $this->position($group, $link, 10);
        $child = $this->page('child', $layout, $this->route('/child', 'child'));
        $child->setParentPage($page);

        $this->generate();

        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($this->outputFile)), $lint, $status);
        self::assertSame(0, $status, implode("\n", $lint) . "\n" . file_get_contents($this->outputFile));
    }

    public function test_the_whole_file_for_an_empty_database(): void
    {
        $code = $this->generate();

        self::assertSame(<<<'PHP'
            <?php

            namespace App\DataFixtures;

            use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
            use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;

            class GeneratedScaffold extends AbstractCwaScaffold
            {
                public function build(CwaFixtureBuilder $cwa): void
                {
                    $c = [];
                    $g = [];
                }
            }

            PHP, $code);
    }

    public function test_every_layout_and_top_level_page_data_is_emitted_and_children_only_inside_their_parent(): void
    {
        $this->layout('main', 'CwaLayoutPrimary');
        $this->layout('secondary', 'CwaLayoutSecondary');
        foreach (['One', 'Two'] as $title) {
            $pageData = new PageData();
            $pageData->setTitle($title);
            $this->repositories[AbstractPageData::class][] = $pageData;
        }
        $parent = $this->page('parent', null, $this->route('/parent', 'parent'));
        $first = $this->page('first-child', null, $this->route('/parent/first', 'first-child'));
        $first->setParentPage($parent);
        $second = $this->page('second-child', null, $this->route('/parent/second', 'second-child'));
        $second->setParentPage($parent);
        $childData = new PageData();
        $childData->setTitle('Child data');
        $childData->setParentPage($parent);
        $this->repositories[AbstractPageData::class][] = $childData;

        $code = $this->generate();

        self::assertStringContainsString("\n        \$layout = \$cwa->layout('main', 'Primary');\n        \$layout = \$cwa->layout('secondary', 'Secondary');\n", $code);
        self::assertStringContainsString("\$pageData->setTitle('One');", $code);
        self::assertStringContainsString("\$pageData->setTitle('Two');", $code);
        self::assertSame(2, substr_count($code, '$cwa->pageData($pageData'));
        self::assertStringContainsString("\$page = \$child->page('first-child'", $code);
        self::assertStringContainsString("\$page = \$child->page('second-child'", $code);
        self::assertStringNotContainsString("\$cwa->page('first-child'", $code);
        self::assertStringContainsString("            \$pageData->setTitle('Child data');\n            \$child->pageData(\$pageData)\n                ->withoutRoute();\n", $code);
    }

    public function test_items_that_cannot_be_reproduced_are_listed_one_per_line(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/gone_ones', '/component/gone_twos'];

        $this->generate();

        self::assertStringContainsString("2 item(s) could not be reproduced:\n  - Allowed component \"/component/gone_ones\"", $this->display);
        self::assertStringContainsString("\n  - Allowed component \"/component/gone_twos\"", $this->display);
    }

    public function test_every_resolvable_allowed_class_is_kept_and_blank_node_iris_are_not_matched(): void
    {
        $this->blankNodeClasses = [DummyPublishableComponent::class];
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/gone_ones', '/component/dummy_components', '/component/dummy_navigation_links', '/.well-known/genid/' . md5(DummyPublishableComponent::class)];

        $code = $this->generate();

        self::assertStringContainsString("\$g[1] = \$layout->group('top', allow: [DummyComponent::class, DummyNavigationLink::class]);", $code);
        self::assertStringContainsString('use Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\DummyNavigationLink;', $code);
        self::assertStringContainsString('does not match a component class', $this->display);
        self::assertStringContainsString('/.well-known/genid/', $this->display);
    }

    public function test_a_location_reference_keeps_everything_after_the_first_separator(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $this->group('nav_main_site', '/_/layouts/main', $layout);

        $code = $this->generate();

        self::assertStringContainsString("\$layout->group('nav', locationReference: 'main_site');", $code);
    }

    public function test_every_group_a_component_owns_is_emitted_without_reporting_them(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $tabs = new DummyComponent();
        $this->position($group, $tabs, 10);
        $iri = '/component/dummy_components/' . spl_object_id($tabs);
        foreach (['left', 'right'] as $name) {
            $owned = new ComponentGroup();
            $owned->reference = $name . '_' . $iri;
            $owned->location = $iri;
            $tabs->addComponentGroup($owned);
            $owned->components->add($tabs);
        }

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->component(\$c[1])->group('left');", $code);
        self::assertStringContainsString("\$cwa->component(\$c[1])->group('right');", $code);
        self::assertStringNotContainsString('could not be reproduced', $this->display);
    }

    public function test_a_component_is_constructed_before_its_fields_and_an_unwritable_value_does_not_stop_later_fields(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->uiClassNames = [new \stdClass()];
        $link->label = 'After';
        (new \ReflectionProperty(AbstractComponent::class, 'id'))->setValue($link, \Ramsey\Uuid\Uuid::uuid4());
        $this->position($group, $link, 10);
        $unchanged = new DummyPublishableComponent();
        $this->position($group, $unchanged, 20);

        $code = $this->generate();

        self::assertLessThan(strpos($code, "\$c[1]->label = 'After';"), strpos($code, '$c[1] = new DummyNavigationLink();'));
        self::assertStringNotContainsString('$id', $this->display);
        self::assertStringNotContainsString('->id', $code);
        self::assertStringContainsString("\$c[2] = new DummyPublishableComponent();\n        \$g[1]->add(\$c[2]);", $code);
    }

    public function test_an_uploaded_file_is_written_alongside_the_other_fields(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/photo.jpg', 'photo');
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $component->setFilename('components/photo.jpg');
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$c[1]->setPublishedAt(new \\DateTime('2020-01-01T00:00:00.000000+00:00'));\n        \$c[1]->file = new File(__DIR__ . '/assets/photo.jpg');", $code);
    }

    public function test_page_data_route_is_not_reported_and_a_scheduled_page_keeps_its_title(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $template = $this->page('template', $layout);
        $template->isTemplate = true;
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('Conference');
        $pageData->page = $template;
        $pageData->setRoute($this->route('/conference', 'conference'));
        $pageData->getRoute()->setLiveAt(null);
        $pageData->component = new DummyComponent();
        $child = $this->page('programme', $layout, $this->route('/conference/programme', 'programme'));
        $child->setParentPageData($pageData);
        $this->repositories[AbstractPageData::class][] = $pageData;
        $scheduled = $this->page('soon', $layout, $this->route('/soon', 'soon'));
        $scheduled->setTitle('Soon');
        $scheduled->getRoute()->setLiveAt(new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));

        $code = $this->generate();

        self::assertStringNotContainsString('could not be reproduced', $this->display);
        self::assertStringContainsString("\$page->title('Soon');\n        \$page->liveAt(", $code);
        self::assertStringContainsString("\$cwa->pageData(\$pageData, template: 'template', route: '/conference', routeName: 'conference')\n            ->liveAt(NULL)\n            ->nested(function (CwaFixtureBuilder \$child) use (\$cwa, &\$c, &\$g): void {\n                \$page = \$child->page('programme'", $code);
    }

    public function test_every_draft_and_reference_is_emitted(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $route = $this->route('/about', 'about');
        $this->page('about', $layout, $route);
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        foreach (['one', 'two'] as $sort => $reference) {
            $published = new DummyPublishableComponent();
            $published->reference = $reference;
            $draft = new DummyPublishableComponent();
            $draft->reference = $reference . '-draft';
            $draft->setPublishedResource($published);
            (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
            $this->position($group, $published, $sort);
            $link = new DummyNavigationLink();
            $link->route = $route;
            $this->position($group, $link, 10 + $sort);
        }

        $code = $this->generate();

        self::assertSame(2, substr_count($code, '$cwa->persist('));
        self::assertSame(2, substr_count($code, "->route = \$cwa->getRoute('about');"));
        self::assertStringContainsString("\$c[5] = new DummyPublishableComponent();\n            \$c[5]->reference = 'one-draft';", $code);
        self::assertStringContainsString("\$c[6]->reference = 'two-draft';", $code);
    }

    public function test_use_statements_are_sorted(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->position($group, new DummyNavigationLink(), 10);
        $this->position($group, new DummyComponent(), 20);

        $code = $this->generate();

        self::assertLessThan(strpos($code, 'Entity\\DummyNavigationLink;'), strpos($code, 'Entity\\DummyComponent;'));
    }

    private function generate(): string
    {
        $manager = $this->entityManager($registry = $this->createStub(ManagerRegistry::class));
        $registry->method('getManagerForClass')->willReturn($manager);
        $registry->method('getRepository')->willReturnCallback(function (string $class) {
            $repository = $this->createStub(ObjectRepository::class);
            $repository->method('findAll')->willReturn($this->repositories[$class] ?? []);

            return $repository;
        });

        $command = new GenerateFixturesCommand(
            $registry,
            $this->iriConverter(),
            new UploadableAttributeReader($registry, true),
            new PublishableAttributeReader($registry),
            new TimestampedAttributeReader($registry),
            new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem])),
        );

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--output' => $this->outputFile]));
        $this->display = $tester->getDisplay();

        return file_get_contents($this->outputFile);
    }

    private function entityManager(ManagerRegistry $registry): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([
            __DIR__ . '/../../src/Entity',
            __DIR__ . '/../Functional/TestBundle/Entity',
        ], true);
        $configuration->enableNativeLazyObjects(true);
        $manager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        $events = $manager->getEventManager();
        $events->addEventListener(Events::loadClassMetadata, new PublishableListener(new PublishableAttributeReader($registry)));
        $events->addEventListener(Events::loadClassMetadata, new UploadableListener(new UploadableAttributeReader($registry, true)));
        $events->addEventListener(Events::loadClassMetadata, new TimestampedListener(new TimestampedAttributeReader($registry)));

        return $manager;
    }

    private function iriConverter(): IriConverterInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $blankNodeClasses = $this->blankNodeClasses;
        $iriConverter->method('getIriFromResource')->willReturnCallback(
            static function (object|string $resource, int $referenceType = 0, ?Operation $operation = null) use ($blankNodeClasses): ?string {
                if (\is_string($resource) && \in_array($resource, $blankNodeClasses, true)) {
                    return '/.well-known/genid/' . md5($resource);
                }
                if (\is_string($resource)) {
                    $short = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', (new \ReflectionClass($resource))->getShortName()));

                    return '/component/' . $short . 's';
                }
                if ($resource instanceof Layout) {
                    return '/_/layouts/' . $resource->reference;
                }
                if ($resource instanceof Page) {
                    return '/_/pages/' . $resource->reference;
                }
                if ($resource instanceof AbstractComponent) {
                    return '/component/dummy_components/' . spl_object_id($resource);
                }

                return '/_/resources/' . spl_object_id($resource);
            }
        );

        return $iriConverter;
    }

    private function layout(string $reference, string $uiComponent): Layout
    {
        $layout = new Layout();
        $layout->reference = $reference;
        $layout->uiComponent = $uiComponent;
        $this->repositories[Layout::class][] = $layout;

        return $layout;
    }

    private function page(string $reference, ?Layout $layout, ?Route $route = null): Page
    {
        $page = new Page();
        $page->reference = $reference;
        $page->uiComponent = 'CwaPagePrimary';
        $page->layout = $layout;
        $page->isTemplate = false;
        if (null !== $route) {
            $page->setRoute($route);
        }
        $this->repositories[Page::class][] = $page;

        return $page;
    }

    private function route(string $path, string $name): Route
    {
        $route = new Route();
        $route->setPath($path);
        $route->setName($name);
        $route->setLiveAt(new \DateTimeImmutable('2000-01-01'));

        return $route;
    }

    private function group(string $reference, string $location, Layout|Page $owner): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $location;
        $owner->getComponentGroups()->add($group);
        $owner instanceof Layout ? $group->layouts->add($owner) : $group->pages->add($owner);

        return $group;
    }

    private function position(ComponentGroup $group, AbstractComponent $component, int $sortValue): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->sortValue = $sortValue;
        $position->component = $component;
        $position->componentGroup = $group;
        $group->componentPositions->add($position);

        return $position;
    }
}
