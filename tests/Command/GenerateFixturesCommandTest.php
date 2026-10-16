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

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Command\GenerateFixturesCommand;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Fixture\Generator\ScaffoldGenerator;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Tests\Command\Fixture\Clash\PageData as ComponentNamedPageData;
use Silverback\ApiComponentsBundle\Tests\Fixture\Generator\GeneratorSiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyHtmlContent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRoute;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

class GenerateFixturesCommandTest extends TestCase
{
    use GeneratorSiteTrait;

    private const string DOCUMENTED_SCAFFOLD = __DIR__ . '/../../features/fixtures/scaffolds/SiteScaffold.php';

    private string $outputDirectory;
    private string $outputFile;
    private string $display = '';
    private ?FixtureAssetStoreFactory $assetStores = null;

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
        self::assertStringNotContainsString('$c = [];', $code);
        self::assertStringNotContainsString('$g = [];', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringContainsString('Fixture class written to ' . $this->outputFile, $this->display);
        self::assertStringNotContainsString('stored file(s)', $this->display);
    }

    public function test_the_class_is_named_after_the_output_file(): void
    {
        $code = $this->generate(['--output' => $this->outputDirectory . '/SnapshotFixtures.php']);

        self::assertStringContainsString('class SnapshotFixtures extends AbstractCwaScaffold', $code);
        self::assertStringNotContainsString('GeneratedScaffold', $code);
    }

    public function test_the_namespace_can_be_given(): void
    {
        $code = $this->generate(['--namespace' => 'Acme\\Site\\Fixtures']);

        self::assertStringContainsString("namespace Acme\\Site\\Fixtures;\n", $code);
        self::assertStringNotContainsString('App\\DataFixtures', $code);
    }

    public function test_the_namespace_may_be_given_with_surrounding_separators(): void
    {
        $code = $this->generate(['--namespace' => '\\Acme\\Fixtures\\']);

        self::assertStringContainsString("namespace Acme\\Fixtures;\n", $code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidClassFileNames(): iterable
    {
        yield 'a hyphen' => ['snapshot-fixtures.php'];
        yield 'a leading digit' => ['1Snapshot.php'];
        yield 'a space' => ['Snapshot Fixtures.php'];
    }

    #[DataProvider('invalidClassFileNames')]
    public function test_an_output_file_whose_name_is_not_a_class_name_fails_without_writing(string $fileName): void
    {
        $tester = $this->runCommand(['--output' => $this->outputDirectory . '/' . $fileName]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('is not a valid PHP class name', $tester->getDisplay());
        self::assertFileDoesNotExist($this->outputDirectory . '/' . $fileName);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNamespaces(): iterable
    {
        yield 'a hyphen' => ['App\\Data-Fixtures'];
        yield 'an empty segment' => ['App\\\\DataFixtures'];
        yield 'a leading digit' => ['App\\1Fixtures'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidNamespaces')]
    public function test_an_invalid_namespace_fails_without_writing(string $namespace): void
    {
        $tester = $this->runCommand(['--namespace' => $namespace]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('is not a valid PHP namespace', $tester->getDisplay());
        self::assertFileDoesNotExist($this->outputFile);
    }

    public function test_the_whole_file_for_the_documented_site(): void
    {
        $this->documentedSite();

        $code = $this->generate(['--output' => $this->outputDirectory . '/SiteScaffold.php']);

        self::assertSame(file_get_contents(self::DOCUMENTED_SCAFFOLD), $code);
        self::assertFileEquals(\dirname(self::DOCUMENTED_SCAFFOLD) . '/assets/home-hero.png', $this->outputDirectory . '/assets/home-hero.png');
        self::assertStringNotContainsString('could not be reproduced', $this->display);
    }

    public function test_the_documented_site_has_no_positional_variables_and_no_after_routes(): void
    {
        $this->documentedSite();

        $code = $this->generate(['--output' => $this->outputDirectory . '/SiteScaffold.php']);

        self::assertDoesNotMatchRegularExpression('/\$[cg]\[/', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringNotContainsString('setPublishedAt', $code);
        self::assertStringNotContainsString('use (', $code);
        self::assertStringNotContainsString('static function', $code);
        self::assertDoesNotMatchRegularExpression('/\[0 =>/', $code);
        self::assertStringNotContainsString('//', str_replace(['http://', 'https://'], '', $code));
        self::assertStringNotContainsString('->reference(', $code);
    }

    public function test_no_generated_identifier_is_longer_than_forty_characters(): void
    {
        $this->documentedSite();

        $code = $this->generate(['--output' => $this->outputDirectory . '/SiteScaffold.php']);

        preg_match_all('/\$(?:this->)?([A-Za-z_]\w*)|function (\w+)\(/', $code, $matches);
        $identifiers = array_unique(array_filter([...$matches[1], ...$matches[2]]));
        self::assertContains('welcomeCwaDummyPublishableComponent', $identifiers);
        self::assertContains('welcomeCwaDummyPublishableComponentDraft', $identifiers);
        foreach ($identifiers as $identifier) {
            self::assertLessThanOrEqual(40, \strlen($identifier), $identifier);
        }
    }

    public function test_no_line_but_a_string_piece_is_longer_than_120_columns(): void
    {
        $this->documentedSite();

        $code = $this->generate(['--output' => $this->outputDirectory . '/SiteScaffold.php']);

        foreach (explode("\n", $code) as $line) {
            if (preg_match('/^\s*(\. )?\'/', $line) || preg_match('/= \'/', $line) || !str_contains($line, '$')) {
                continue;
            }
            self::assertLessThanOrEqual(120, \strlen($line), $line);
        }
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
                }
            }

            PHP, $code);
    }

    public function test_keep_timestamps_writes_exact_dates(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $route = $this->route('/about', 'about');
        $route->setLiveAt(new \DateTimeImmutable('2001-02-03T04:05:06+00:00'));
        $this->page('about', $layout, $route);
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $published = new DummyPublishableComponent();
        $published->reference = 'live';
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $this->position($group, $published, 10);

        $default = $this->generate();

        self::assertStringContainsString('$cwa->publish($liveDummyPublishableComponent);', $default);
        self::assertStringNotContainsString('2020-01-01', $default);
        self::assertStringNotContainsString('2001-02-03', $default);
        self::assertStringNotContainsString('->liveAt(', $default);

        $kept = $this->generate(['--keep-timestamps' => true]);

        self::assertStringContainsString("\$cwa->publish(\$liveDummyPublishableComponent, at: new \\DateTimeImmutable('2020-01-01T00:00:00+00:00'));", $kept);
        self::assertStringContainsString("->liveAt(new \\DateTimeImmutable('2001-02-03T04:05:06+00:00'))", $kept);
    }

    public function test_a_scheduled_published_at_is_written_exactly_and_a_past_one_is_published_at_load_time(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $past = new DummyPublishableComponent();
        $past->reference = 'past';
        $past->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $this->position($group, $past, 10);
        $scheduled = new DummyPublishableComponent();
        $scheduled->reference = 'soon';
        $scheduled->setPublishedAt(new \DateTime('2099-01-15T09:00:00+00:00'));
        $this->position($group, $scheduled, 20);
        $unpublished = new DummyPublishableComponent();
        $unpublished->reference = 'never';
        $this->position($group, $unpublished, 30);

        $code = $this->generate();

        self::assertStringContainsString("\$pastDummyPublishableComponent->reference = 'past';\n        \$cwa->publish(\$pastDummyPublishableComponent);\n", $code);
        self::assertStringContainsString("\$cwa->publish(\$soonDummyPublishableComponent, at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'));", $code);
        self::assertStringNotContainsString('$cwa->publish($neverDummyPublishableComponent', $code);
        self::assertStringNotContainsString('2020-01-01', $code);
    }

    public function test_a_component_that_needs_only_publishing_is_published_inline(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $this->position($group, $published, 10);

        $code = $this->generate();

        self::assertStringContainsString('->add($cwa->publish(new DummyPublishableComponent()));', $code);
    }

    public function test_two_classes_with_the_same_short_name_are_aliased(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->position($group, new ComponentNamedPageData(), 10);
        $pageData = new PageData();
        $pageData->setTitle('Event');
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertSame(2, preg_match_all('/^use [\\\\\w]+\\\\PageData( as \w+)?;$/m', $code));
        self::assertSame(1, preg_match_all('/^use [\\\\\w]+\\\\PageData as \w+;$/m', $code));
        $this->assertLints();
    }

    public function test_a_group_with_allowed_components_and_positions_uses_named_arguments_and_class_names(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/dummy_components'];
        $this->position($group, new DummyComponent(), 10);

        $code = $this->generate();

        self::assertStringContainsString(<<<'PHP'
                private function mainLayout(CwaFixtureBuilder $cwa): void
                {
                    $cwa->layout('main', uiComponent: 'Primary')
                        ->group('top', allow: [DummyComponent::class])
                        ->add(new DummyComponent());
                }
            PHP, $code);
        self::assertStringContainsString("        \$this->mainLayout(\$cwa);\n", $code);
        self::assertStringContainsString('use Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\DummyComponent;', $code);
    }

    public function test_an_allowed_iri_that_matches_no_component_class_is_reported_and_left_out(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/removed_components'];

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->layout('main', uiComponent: 'Primary')\n            ->group('top');", $code);
        self::assertStringContainsString('Allowed component "/component/removed_components" on the component group "top_/_/layouts/main" does not match a component class.', $this->display);
    }

    public function test_a_page_is_emitted_with_its_fields_route_and_allowed_group(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $page->setTitle('Home');
        $page->setMetaDescription('Welcome');
        $page->uiClassNames = ['wide'];
        $group = $this->group('primary_/_/pages/home', '/_/pages/home', $page);
        $group->allowedComponents = ['/component/dummy_components'];

        $code = $this->generate();

        self::assertStringContainsString('    private function homePage(CwaFixtureBuilder $cwa): void', $code);
        self::assertStringContainsString("        \$cwa->page(\n            'home',\n            uiComponent: 'Primary',\n            layout: 'main',\n            route: '/',\n            routeName: 'home',\n            uiClassNames: ['wide'],\n        )\n", $code);
        self::assertStringContainsString("            ->title('Home')\n            ->metaDescription('Welcome')\n            ->group('primary', allow: [DummyComponent::class]);\n", $code);
        self::assertStringNotContainsString('->liveAt(', $code);
    }

    public function test_a_route_name_equal_to_the_derived_name_is_left_out(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $this->page('about', $layout, $this->route('/about/us', 'about-us'));
        $this->page('team', $layout, $this->route('/team', 'our-team'));

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->page('about', uiComponent: 'Primary', layout: 'main', route: '/about/us');", $code);
        self::assertStringContainsString("\$cwa->page('team', uiComponent: 'Primary', layout: 'main', route: '/team', routeName: 'our-team');", $code);
    }

    public function test_a_page_without_a_route_is_emitted_without_one(): void
    {
        $this->page('parent', $this->layout('main', uiComponent: 'CwaLayoutPrimary'))->setTitle('Parent');

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->page('parent', uiComponent: 'Primary', layout: 'main')\n            ->title('Parent')\n            ->withoutRoute();\n", $code);
    }

    public function test_a_template_page_without_a_layout_is_emitted_with_an_empty_layout_reference(): void
    {
        $page = $this->page('template', null);
        $page->isTemplate = true;

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->page('template', uiComponent: 'Primary', layout: '', isTemplate: true);", $code);
        self::assertStringNotContainsString('withoutRoute', $code);
    }

    public function test_a_scheduled_or_draft_route_is_emitted_and_a_live_one_is_not(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertStringContainsString("->liveAt(new \\DateTimeImmutable('2999-01-01T00:00:00+00:00'));", $code);
        self::assertStringContainsString('->liveAt(null);', $code);
        self::assertSame(2, substr_count($code, '->liveAt('));
    }

    public function test_methods_are_called_from_build_in_site_map_order(): void
    {
        $layout = $this->layout('secondary', uiComponent: 'CwaLayoutSecondary');
        $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $this->page('zeta-template', $layout)->isTemplate = true;
        $this->page('alpha-template', $layout)->isTemplate = true;
        $this->page('routeless', $layout)->setTitle('Routeless');
        $this->page('contact', $layout, $this->route('/contact', 'contact'));
        $this->page('about', $layout, $this->route('/about', 'about'));
        $this->page('home', $layout, $this->route('/', 'home'));
        foreach (['Zulu' => null, 'Alpha' => null, 'Routed' => '/routed'] as $title => $path) {
            $pageData = new PageData();
            $pageData->setTitle($title);
            if (null !== $path) {
                $this->route($path, 'routed')->setPageData($pageData);
            }
            $this->repositories[AbstractPageData::class][] = $pageData;
        }
        $redirect = $this->route('/old', 'old');
        $redirect->setRedirect($this->repositories[Page::class][4]->getRoute());
        $this->repositories[Route::class] = [$redirect];

        $code = $this->generate();

        self::assertStringContainsString(<<<'PHP'
                public function build(CwaFixtureBuilder $cwa): void
                {
                    $this->mainLayout($cwa);
                    $this->secondaryLayout($cwa);
                    $this->homePage($cwa);
                    $this->aboutPage($cwa);
                    $this->contactPage($cwa);
                    $this->routelessPage($cwa);
                    $this->alphaTemplatePage($cwa);
                    $this->zetaTemplatePage($cwa);
                    $this->routedPageData($cwa);
                    $this->alphaPageData($cwa);
                    $this->zuluPageData($cwa);
                    $this->redirects($cwa);
                }
            PHP, $code);
        self::assertLessThan(strpos($code, 'private function aboutPage('), strpos($code, 'private function homePage('));
    }

    public function test_a_group_shared_through_a_location_reference_is_emitted_for_each_owner_with_its_positions_once(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $group = $this->group('nav_site', '/_/layouts/main', $layout);
        $page->getComponentGroups()->add($group);
        $group->pages->add($page);
        $this->position($group, new DummyComponent(), 10);

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->layout('main', uiComponent: 'Primary')\n            ->group('nav', locationReference: 'site')\n            ->add(new DummyComponent());", $code);
        self::assertStringContainsString("->group('nav', locationReference: 'site');", $code);
        self::assertSame(2, substr_count($code, "->group('nav', locationReference: 'site')"));
        self::assertSame(1, substr_count($code, '->add('));
    }

    public function test_a_group_used_by_another_owner_without_a_location_reference_is_reported_for_that_owner(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $page->getComponentGroups()->add($group);
        $group->pages->add($page);

        $code = $this->generate();

        self::assertSame(1, substr_count($code, "->group('top'"));
        self::assertStringContainsString("\$cwa->layout('main', uiComponent: 'Primary')\n            ->group('top');", $code);
        self::assertStringContainsString('Component group "top_/_/layouts/main" is also used by /_/pages/home, which the fixture builder cannot share it with.', $this->display);
    }

    public function test_a_group_reference_without_a_name_separator_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $this->group('orphan', '/_/layouts/other', $layout);

        $code = $this->generate();

        self::assertStringNotContainsString('->group(', $code);
        self::assertStringContainsString('Component group "orphan" on /_/layouts/main has a reference the fixture builder cannot express.', $this->display);
    }

    public function test_groups_are_emitted_by_name_and_positions_by_sort_value(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $sidebar = $this->group('sidebar_/_/pages/home', '/_/pages/home', $page);
        $hero = $this->group('hero_/_/pages/home', '/_/pages/home', $page);
        $this->position($sidebar, $this->labelled('Aside'), 10);
        $this->position($hero, $this->labelled('Second'), 20);
        $this->position($hero, $this->labelled('First'), 10);

        $code = $this->generate();

        self::assertLessThan(strpos($code, "\$page->group('sidebar')"), strpos($code, "\$page->group('hero')"));
        self::assertLessThan(strpos($code, "'Second'"), strpos($code, "'First'"));
        self::assertLessThan(strpos($code, "'Aside'"), strpos($code, "'Second'"));
        self::assertLessThan(strpos($code, '$page = $cwa->page('), strpos($code, "'Aside'"));
    }

    public function test_positions_are_emitted_in_sort_order_and_a_component_in_two_positions_is_constructed_once(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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
        self::assertStringContainsString("            ->add(\$mainTopDummyComponent)\n            ->add(\$mainTopDummyComponent2)\n            ->add(\$mainTopDummyComponent);\n", $code);
    }

    public function test_a_position_without_a_component_is_skipped(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $position = $this->position($group, new DummyComponent(), 10);
        $position->pageDataClass = PageDataWithComponent::class;
        $position->pageDataProperty = 'component';

        $code = $this->generate();

        self::assertStringContainsString("            ->pageDataPosition(PageDataWithComponent::class, 'component');", $code);
        self::assertStringNotContainsString('new DummyComponent()', $code);
        self::assertStringContainsString('The fallback component of the page data position "component" in the component group "top_/_/layouts/main".', $this->display);
    }

    public function test_a_component_keeps_inherited_public_non_public_and_date_fields_and_skips_defaults(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->label = 'About';
        $link->theme = 'dark';
        $link->eventDate = new \DateTimeImmutable('2027-01-01T10:00:00+00:00');
        $link->setInternalNote('check');
        $link->uiClassNames = ['a', 'b'];
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringContainsString('$aboutDummyNavigationLink = new DummyNavigationLink();', $code);
        self::assertStringContainsString("\$aboutDummyNavigationLink->label = 'About';", $code);
        self::assertStringContainsString("\$aboutDummyNavigationLink->theme = 'dark';", $code);
        self::assertStringContainsString("\$aboutDummyNavigationLink->eventDate = new \\DateTimeImmutable('2027-01-01T10:00:00+00:00');", $code);
        self::assertStringContainsString("\$aboutDummyNavigationLink->setInternalNote('check');", $code);
        self::assertStringContainsString("\$aboutDummyNavigationLink->uiClassNames = ['a', 'b'];", $code);
        self::assertStringNotContainsString('->uiComponent', $code);
        self::assertStringNotContainsString('createdAt', $code);
    }

    public function test_a_value_that_cannot_be_written_as_php_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $component = new DummyComponent();
        $component->uiClassNames = [new \stdClass()];
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringNotContainsString('uiClassNames', $code);
        self::assertStringContainsString('The value of ' . DummyComponent::class . '::$uiClassNames cannot be written as PHP (1 component).', $this->display);
    }

    public function test_long_html_is_split_at_block_closing_tags_and_reloads_byte_for_byte(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $html = str_repeat('<p>' . str_repeat('Some words in a paragraph. ', 30) . '</p>', 6) . '<P>Upper case.</P>';
        $component = new DummyHtmlContent();
        $component->html = $html;
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertSame(6, substr_count($code, "\n            . '<"));
        preg_match('/\$mainTopDummyHtmlContent->html = (.*?);\n/s', $code, $match);
        self::assertSame($html, eval('return ' . $match[1] . ';'));
    }

    public function test_html_with_newlines_is_a_nowdoc_and_reloads_byte_for_byte(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $html = "<p>Before the code sample, some words that make this long enough.</p><pre><code>line one\n    line two\n</code></pre>\n";
        $component = new DummyHtmlContent();
        $component->html = $html;
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$mainTopDummyHtmlContent->html = <<<'HTML'\n", $code);
        preg_match('/\$mainTopDummyHtmlContent->html = (<<<\'HTML\'.*?\n\s*HTML);\n/s', $code, $match);
        self::assertSame($html, eval('return ' . $match[1] . ';'));
    }

    public function test_a_route_reference_is_written_next_to_its_component(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $route = $this->route('/about', 'about');
        $this->page('about', $layout, $route);
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->route = $route;
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$mainTopDummyNavigationLink = new DummyNavigationLink();\n        \$cwa->component(\$mainTopDummyNavigationLink)->linkRoute('route', routeName: 'about');\n", $code);
        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringNotContainsString('getRoute(', $code);
    }

    public function test_a_route_reference_to_a_redirect_is_written_with_the_redirect_name(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $this->page('about', $layout, $about);
        $old = $this->route('/old-about', 'old-about');
        $old->setRedirect($about);
        $this->repositories[Route::class] = [$old];
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->label = 'Old';
        $link->route = $old;
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->component(\$oldDummyNavigationLink)->linkRoute('route', routeName: 'old-about');", $code);
        self::assertStringNotContainsString('refers to', $this->display);
    }

    public function test_a_reference_to_a_route_outside_the_site_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->route = $this->route('/elsewhere', 'elsewhere');
        $this->position($group, $link, 10);

        $code = $this->generate();

        self::assertStringNotContainsString('->linkRoute(', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
        self::assertStringContainsString(DummyNavigationLink::class . '::$route refers to', $this->display);
    }

    public function test_a_page_data_route_association_is_a_reference_on_its_builder(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $this->page('about', $layout, $about);
        $pageData = new PageDataWithRoute();
        $pageData->setTitle('Event');
        $pageData->relatedRoute = $about;
        $this->route('/event', 'event')->setPageData($pageData);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->pageData(\$pageData, route: '/event')\n            ->linkRoute('relatedRoute', routeName: 'about');\n", $code);
        self::assertStringNotContainsString('relatedRoute =', $code);
    }

    public function test_a_draft_is_declared_after_its_published_component(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertStringContainsString(<<<'PHP'
                    $liveDummyPublishableComponent = new DummyPublishableComponent();
                    $liveDummyPublishableComponent->reference = 'live';
                    $cwa->publish($liveDummyPublishableComponent);

                    $liveDummyPublishableComponentDraft = new DummyPublishableComponent();
                    $liveDummyPublishableComponentDraft->reference = 'live-draft';
                    $cwa->component($liveDummyPublishableComponent)->draft($liveDummyPublishableComponentDraft);

            PHP, $code);
        self::assertStringNotContainsString('setPublishedAt', $code);
        self::assertStringNotContainsString('setPublishedResource', $code);
        self::assertStringNotContainsString('->persist(', $code);
        self::assertStringNotContainsString('afterRoutes', $code);
    }

    public function test_a_scheduled_draft_is_published_at_its_date(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $published = new DummyPublishableComponent();
        $published->reference = 'live';
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $draft = new DummyPublishableComponent();
        $draft->reference = 'next';
        $draft->setPublishedAt(new \DateTime('2099-01-15T09:00:00+00:00'));
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $this->position($group, $published, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->publish(\$liveDummyPublishableComponentDraft, at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'));", $code);
    }

    public function test_a_group_owned_by_a_draft_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $published = new DummyPublishableComponent();
        $published->reference = 'live';
        $draft = new DummyPublishableComponent();
        $draft->reference = 'live-draft';
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $this->position($group, $published, 10);
        $iri = '/component/dummy_components/' . spl_object_id($draft);
        $panels = new ComponentGroup();
        $panels->reference = 'panels_' . $iri;
        $panels->location = $iri;
        $draft->addComponentGroup($panels);
        $panels->components->add($draft);

        $code = $this->generate();

        self::assertStringNotContainsString("->group('panels')", $code);
        self::assertStringContainsString('could not be reproduced', $this->display);
        self::assertStringContainsString('panels_' . $iri, $this->display);
    }

    public function test_a_stored_file_is_exported_without_its_token_and_names_never_collide(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/image-0a1b2c3d.png', 'first');
        $this->filesystem->write('components/image-9f8e7d6c.png', 'second');
        foreach (['components/image-0a1b2c3d.png', 'components/image-9f8e7d6c.png'] as $sort => $path) {
            $component = new DummyUploadableAndPublishable();
            $component->setFilename($path);
            $this->position($group, $component, $sort);
        }

        $code = $this->generate();

        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable->file = new File(__DIR__ . '/assets/image.png');", $code);
        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable2->file = new File(__DIR__ . '/assets/image-2.png');", $code);
        self::assertStringContainsString('use Symfony\\Component\\HttpFoundation\\File\\File;', $code);
        self::assertStringNotContainsString('filename', $code);
        self::assertSame('first', file_get_contents($this->outputDirectory . '/assets/image.png'));
        self::assertSame('second', file_get_contents($this->outputDirectory . '/assets/image-2.png'));
        self::assertStringContainsString('2 stored file(s) exported to ' . $this->outputDirectory . '/assets', $this->display);
        self::assertFileDoesNotExist($this->outputDirectory . '/GeneratedScaffold.assets.json');
    }

    public function test_identical_stored_files_are_exported_as_one_asset_that_both_resources_name(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/logo-0a1b2c3d.txt', 'same');
        $this->filesystem->write('components/phpGPCPOl', 'same');
        $this->filesystem->write('components/logo-9f8e7d6c.txt', 'different');
        foreach (['components/logo-0a1b2c3d.txt', 'components/phpGPCPOl', 'components/logo-9f8e7d6c.txt'] as $sort => $path) {
            $component = new DummyUploadableAndPublishable();
            $component->setFilename($path);
            $this->position($group, $component, $sort);
        }

        $code = $this->generate();

        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable->file = new File(__DIR__ . '/assets/logo.txt');", $code);
        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable2->file = new File(__DIR__ . '/assets/logo.txt');", $code);
        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable3->file = new File(__DIR__ . '/assets/logo-2.txt');", $code);
        self::assertSame(['logo-2.txt', 'logo.txt'], array_values(array_diff(scandir($this->outputDirectory . '/assets') ?: [], ['.', '..'])));
        self::assertSame('same', file_get_contents($this->outputDirectory . '/assets/logo.txt'));
        self::assertSame('different', file_get_contents($this->outputDirectory . '/assets/logo-2.txt'));
        self::assertStringContainsString('2 stored file(s) exported to ' . $this->outputDirectory . '/assets', $this->display);
    }

    public function test_with_a_local_asset_store_files_are_stored_by_content_hash_and_listed_in_a_manifest(): void
    {
        $this->twoUploads('first', 'second');

        $code = $this->generate(['--assets-store' => 'local']);

        self::assertStringContainsString(\sprintf("\$mainTopDummyUploadableAndPublishable->file = \$cwa->asset('%s');", hash('sha256', 'first')), $code);
        self::assertStringContainsString(\sprintf("\$mainTopDummyUploadableAndPublishable2->file = \$cwa->asset('%s');", hash('sha256', 'second')), $code);
        self::assertStringNotContainsString('HttpFoundation\\File\\File', $code);
        self::assertSame('first', file_get_contents($this->outputDirectory . '/assets/' . hash('sha256', 'first')));
        self::assertSame('second', file_get_contents($this->outputDirectory . '/assets/' . hash('sha256', 'second')));
        $manifest = FixtureAssetManifest::fromFile($this->outputDirectory . '/GeneratedScaffold.assets.json');
        self::assertEqualsCanonicalizing([hash('sha256', 'first'), hash('sha256', 'second')], $manifest->hashes());
        self::assertSame(['name' => 'image.txt', 'mimeType' => 'text/plain', 'size' => 5, 'fields' => [DummyUploadableAndPublishable::class . '::file']], $manifest->get(hash('sha256', 'first')));
        self::assertStringContainsString('2 stored file(s) in the fixture asset store ' . $this->outputDirectory . '/assets, listed in ' . $this->outputDirectory . '/GeneratedScaffold.assets.json', $this->display);
    }

    public function test_generating_into_a_local_asset_store_deletes_the_assets_its_manifest_no_longer_names(): void
    {
        $this->twoUploads('first', 'second');
        mkdir($this->outputDirectory . '/assets');
        file_put_contents($this->outputDirectory . '/assets/' . hash('sha256', 'stale'), 'stale');
        file_put_contents($this->outputDirectory . '/assets/README.md', 'notes');

        $this->generate(['--assets-store' => 'local']);

        self::assertFileDoesNotExist($this->outputDirectory . '/assets/' . hash('sha256', 'stale'));
        self::assertFileExists($this->outputDirectory . '/assets/' . hash('sha256', 'first'));
        self::assertFileExists($this->outputDirectory . '/assets/README.md');
        self::assertStringContainsString('1 asset(s) no longer in the manifest deleted from the fixture asset store', $this->display);
    }

    public function test_a_second_generation_writes_the_same_manifest_and_assets(): void
    {
        $this->twoUploads('first', 'second');
        $code = $this->generate(['--assets-store' => 'local']);
        $manifest = file_get_contents($this->outputDirectory . '/GeneratedScaffold.assets.json');

        self::assertSame($code, $this->generate(['--assets-store' => 'local']));
        self::assertSame($manifest, file_get_contents($this->outputDirectory . '/GeneratedScaffold.assets.json'));
        self::assertEqualsCanonicalizing([hash('sha256', 'first'), hash('sha256', 'second')], array_values(array_diff(scandir($this->outputDirectory . '/assets'), ['.', '..'])));
        self::assertStringNotContainsString('deleted from the fixture asset store', $this->display);
    }

    public function test_an_assets_directory_for_one_run_is_a_local_store_there(): void
    {
        $this->twoUploads('first', 'second');

        $this->generate(['--assets-dir' => $this->outputDirectory . '/elsewhere']);

        self::assertFileExists($this->outputDirectory . '/elsewhere/' . hash('sha256', 'first'));
        self::assertDirectoryDoesNotExist($this->outputDirectory . '/assets');
        self::assertFileExists($this->outputDirectory . '/GeneratedScaffold.assets.json');
    }

    public function test_a_configured_flysystem_store_receives_the_assets_and_is_never_pruned_by_generating(): void
    {
        $this->twoUploads('first', 'second');
        $bucket = new Filesystem(new InMemoryFilesystemAdapter());
        $bucket->write('fixtures/' . hash('sha256', 'stale'), 'stale');
        $this->assetStores = new FixtureAssetStoreFactory(new FilesystemProvider(new ServiceLocator(['gcloud' => static fn () => $bucket])), true, 'flysystem', null, 'gcloud', 'fixtures/');

        $code = $this->generate();

        self::assertStringContainsString(\sprintf("\$cwa->asset('%s')", hash('sha256', 'first')), $code);
        self::assertSame('first', $bucket->read('fixtures/' . hash('sha256', 'first')));
        self::assertTrue($bucket->fileExists('fixtures/' . hash('sha256', 'stale')));
        self::assertDirectoryDoesNotExist($this->outputDirectory . '/assets');
        self::assertFileExists($this->outputDirectory . '/GeneratedScaffold.assets.json');
        self::assertStringContainsString('2 stored file(s) in the fixture asset store the gcloud filesystem under fixtures', $this->display);
        self::assertStringContainsString('silverback:api-components:prune-fixture-assets', $this->display);
    }

    public static function invalidAssetStores(): iterable
    {
        yield 'an unknown store' => [['--assets-store' => 's3'], 'The fixture asset store "s3" is not one of "local" or "flysystem".'];
        yield 'a directory for a flysystem store' => [['--assets-store' => 'flysystem', '--assets-dir' => '/tmp/x'], 'A directory can only be given for the local fixture asset store.'];
    }

    #[DataProvider('invalidAssetStores')]
    public function test_an_invalid_asset_store_fails_without_writing(array $options, string $message): void
    {
        $tester = $this->runCommand($options);

        self::assertSame(2, $tester->getStatusCode());
        self::assertStringContainsString($message, $tester->getDisplay());
        self::assertFileDoesNotExist($this->outputFile);
        self::assertFileDoesNotExist($this->outputDirectory . '/GeneratedScaffold.assets.json');
    }

    private function twoUploads(string $first, string $second): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/image-0a1b2c3d.txt', $first);
        $this->filesystem->write('components/image-9f8e7d6c.txt', $second);
        foreach (['components/image-0a1b2c3d.txt', 'components/image-9f8e7d6c.txt'] as $sort => $path) {
            $component = new DummyUploadableAndPublishable();
            $component->setFilename($path);
            $this->position($group, $component, $sort);
        }
    }

    public function test_a_stored_file_that_cannot_be_read_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertStringContainsString(<<<'PHP'
                    $mainTopDummyComponent = new DummyComponent();
                    $cwa->component($mainTopDummyComponent)
                        ->group('panels')
                        ->add(new DummyComponent());

                    $cwa->layout('main', uiComponent: 'Primary')
                        ->group('top')
                        ->add($mainTopDummyComponent);
            PHP, $code);
    }

    public function test_page_data_is_emitted_with_its_fields_and_component_property_inline(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $template = $this->page('template', $layout);
        $template->isTemplate = true;
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('Conference');
        $pageData->setMetaDescription('An event');
        $pageData->page = $template;
        $intro = new DummyComponent();
        $intro->uiComponent = 'Intro';
        $pageData->component = $intro;
        $this->route('/conference', 'conference')->setPageData($pageData);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString('    private function conferencePageData(CwaFixtureBuilder $cwa): void', $code);
        self::assertStringContainsString('$pageData = new PageDataWithComponent();', $code);
        self::assertStringContainsString("\$pageData->setTitle('Conference');", $code);
        self::assertStringContainsString("\$pageData->setMetaDescription('An event');", $code);
        self::assertMatchesRegularExpression('/\$pageData->component = \$(\w+DummyComponent);/', $code);
        preg_match('/\$pageData->component = \$(\w+DummyComponent);/', $code, $match);
        self::assertStringContainsString('$' . $match[1] . " = new DummyComponent();\n        \$" . $match[1] . "->uiComponent = 'Intro';", $code);
        self::assertStringContainsString("\$cwa->pageData(\$pageData, template: 'template', route: '/conference');", $code);
    }

    public function test_an_empty_page_data_component_is_constructed_in_place(): void
    {
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('Conference');
        $pageData->component = new DummyComponent();
        $this->route('/conference', 'conference')->setPageData($pageData);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString('$pageData->component = new DummyComponent();', $code);
    }

    public function test_children_get_their_own_methods_called_from_their_parent_nested_closure(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $child = $this->page('child', $layout, $this->route('/parent/child', 'child'));
        $child->setParentPage($parent);
        $pageData = new PageData();
        $pageData->setTitle('Child data');
        $pageData->setParentPage($parent);
        $this->route('/parent/data', 'data')->setPageData($pageData);
        $pageData->getRoute()->setLiveAt(null);
        $this->repositories[AbstractPageData::class][] = $pageData;

        $code = $this->generate();

        self::assertStringContainsString(<<<'PHP'
                private function parentPage(CwaFixtureBuilder $cwa): void
                {
                    $cwa->page('parent', uiComponent: 'Primary', layout: 'main', route: '/parent')
                        ->nested($this->childPage(...))
                        ->nested($this->childDataPageData(...));
                }

                private function childPage(CwaFixtureBuilder $cwa): void
                {
                    $cwa->page('child', uiComponent: 'Primary', layout: 'main', route: '/parent/child', routeName: 'child');
                }

                private function childDataPageData(CwaFixtureBuilder $cwa): void
                {
                    $pageData = new PageData();
                    $pageData->setTitle('Child data');

                    $cwa->pageData($pageData, route: '/parent/data', routeName: 'data')
                        ->liveAt(null);
                }
            PHP, $code);
        self::assertSame(1, substr_count($code, "\$cwa->page('parent'"));
        self::assertSame(1, substr_count($code, '$this->childPage('));
        self::assertSame(1, substr_count($code, '$this->childDataPageData('));
        self::assertStringNotContainsString('use (', $code);
    }

    public function test_a_single_child_is_passed_to_nested_as_a_first_class_callable_to_any_depth(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $child = $this->page('child', $layout, $this->route('/parent/child', 'parent-child'));
        $child->setParentPage($parent);
        $grandchild = $this->page('grandchild', $layout, $this->route('/parent/child/grandchild', 'parent-child-grandchild'));
        $grandchild->setParentPage($child);

        $code = $this->generate();

        self::assertStringContainsString(<<<'PHP'
                private function parentPage(CwaFixtureBuilder $cwa): void
                {
                    $cwa->page('parent', uiComponent: 'Primary', layout: 'main', route: '/parent')
                        ->nested($this->childPage(...));
                }

                private function childPage(CwaFixtureBuilder $cwa): void
                {
                    $cwa->page('child', uiComponent: 'Primary', layout: 'main', route: '/parent/child')
                        ->nested($this->grandchildPage(...));
                }

                private function grandchildPage(CwaFixtureBuilder $cwa): void
                {
                    $cwa->page('grandchild', uiComponent: 'Primary', layout: 'main', route: '/parent/child/grandchild');
                }
            PHP, $code);
        self::assertStringNotContainsString('function (CwaFixtureBuilder $cwa): void {', $code);
    }

    public function test_a_page_with_groups_and_children_holds_the_page_and_nests_last(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $parent = $this->page('about', $layout, $this->route('/about', 'about'));
        $html = new DummyHtmlContent();
        $html->html = '<p>About</p>';
        $this->position($this->group('primary_/_/pages/about', '/_/pages/about', $parent), $html, 10);
        $child = $this->page('about-team', $layout, $this->route('/about/team', 'team'));
        $child->setParentPage($parent);

        $code = $this->generate();

        self::assertStringContainsString(<<<'PHP'
                    $page = $cwa->page('about', uiComponent: 'Primary', layout: 'main', route: '/about');

                    $page->group('primary')
                        ->add($aboutPrimaryDummyHtmlContent);

                    $page->nested($this->aboutTeamPage(...));
                }

                private function aboutTeamPage(CwaFixtureBuilder $cwa): void
                {
            PHP, $code);
    }

    public function test_a_component_used_in_two_methods_is_a_property_declared_in_the_first(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $link = new DummyNavigationLink();
        $link->label = 'Contact us';
        $this->position($this->group('header_/_/layouts/main', '/_/layouts/main', $layout), $link, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $link, 10);

        $code = $this->generate();

        self::assertStringContainsString("{\n    private DummyNavigationLink \$contactUsDummyNavigationLink;\n\n    public function build(", $code);
        self::assertStringContainsString("        \$this->contactUsDummyNavigationLink = new DummyNavigationLink();\n        \$this->contactUsDummyNavigationLink->label = 'Contact us';\n", $code);
        self::assertLessThan(strpos($code, 'private function homePage('), strpos($code, '$this->contactUsDummyNavigationLink = new'));
        self::assertGreaterThan(strpos($code, 'private function mainLayout('), strpos($code, '$this->contactUsDummyNavigationLink = new'));
        self::assertSame(1, substr_count($code, 'new DummyNavigationLink()'));
        self::assertSame(2, substr_count($code, '->add($this->contactUsDummyNavigationLink)'));
    }

    public function test_a_component_used_only_by_nested_pages_is_declared_in_the_top_level_method_above_the_first(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $alpha = $this->page('alpha', $layout, $this->route('/alpha', 'alpha'));
        $beta = $this->page('beta', $layout, $this->route('/beta', 'beta'));
        $alphaChild = $this->page('alpha-child', $layout, $this->route('/alpha/child', 'alpha-child'));
        $alphaChild->setParentPage($alpha);
        $betaChild = $this->page('beta-child', $layout, $this->route('/beta/child', 'beta-child'));
        $betaChild->setParentPage($beta);
        $link = new DummyNavigationLink();
        $link->label = 'Shared';
        $this->position($this->group('primary_/_/pages/beta-child', '/_/pages/beta-child', $betaChild), $link, 10);
        $this->position($this->group('primary_/_/pages/alpha-child', '/_/pages/alpha-child', $alphaChild), $link, 10);

        $code = $this->generate();

        self::assertStringContainsString('    private DummyNavigationLink $sharedDummyNavigationLink;', $code);
        $declaration = strpos($code, "        \$this->sharedDummyNavigationLink = new DummyNavigationLink();\n");
        self::assertNotFalse($declaration);
        self::assertSame(1, substr_count($code, '$this->sharedDummyNavigationLink = new DummyNavigationLink();'));
        self::assertGreaterThan(strpos($code, 'private function alphaPage('), $declaration);
        self::assertLessThan(strpos($code, 'private function alphaChildPage('), $declaration);
    }

    public function test_a_component_related_to_another_is_assigned_after_both_are_declared(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $teaser = new DummyComponentWithRelated();
        $teaser->name = 'Teaser';
        $card->related = $teaser;
        $this->position($group, $card, 10);
        $this->position($group, $teaser, 20);

        $code = $this->generate();

        $assignment = strpos($code, '$cardDummyComponentWithRelated->related = $teaserDummyComponentWithRelated;');
        self::assertNotFalse($assignment);
        self::assertGreaterThan(strpos($code, '$teaserDummyComponentWithRelated = new DummyComponentWithRelated();'), $assignment);
        self::assertGreaterThan(strpos($code, '$cardDummyComponentWithRelated = new DummyComponentWithRelated();'), $assignment);
        self::assertStringNotContainsString('could not be reproduced', $this->display);
    }

    public function test_components_related_in_a_cycle_are_both_assigned_after_both_are_declared(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $card = new DummyComponentWithRelated();
        $card->name = 'Card';
        $teaser = new DummyComponentWithRelated();
        $teaser->name = 'Teaser';
        $card->related = $teaser;
        $teaser->related = $card;
        $this->position($group, $card, 10);
        $this->position($group, $teaser, 20);

        $code = $this->generate();

        $declared = max(strpos($code, '$cardDummyComponentWithRelated = new'), strpos($code, '$teaserDummyComponentWithRelated = new'));
        self::assertGreaterThan($declared, strpos($code, '$cardDummyComponentWithRelated->related = $teaserDummyComponentWithRelated;'));
        self::assertGreaterThan($declared, strpos($code, '$teaserDummyComponentWithRelated->related = $cardDummyComponentWithRelated;'));
        $this->assertLints();
    }

    public function test_components_related_across_methods_are_both_properties(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $page = $this->page('home', $layout, $this->route('/', 'home'));
        $banner = new DummyComponentWithRelated();
        $banner->name = 'Banner';
        $promo = new DummyComponentWithRelated();
        $promo->name = 'Promo';
        $banner->related = $promo;
        $this->position($this->group('top_/_/layouts/main', '/_/layouts/main', $layout), $banner, 10);
        $this->position($this->group('primary_/_/pages/home', '/_/pages/home', $page), $promo, 10);

        $code = $this->generate();

        self::assertStringContainsString('    private DummyComponentWithRelated $bannerDummyComponentWithRelated;', $code);
        self::assertStringContainsString('    private DummyComponentWithRelated $promoDummyComponentWithRelated;', $code);
        self::assertStringContainsString('$this->bannerDummyComponentWithRelated->related = $this->promoDummyComponentWithRelated;', $code);
        $this->assertLints();
    }

    public function test_redirects_are_emitted_after_their_targets_and_an_orphan_redirect_is_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertStringContainsString(<<<'PHP'
                private function redirects(CwaFixtureBuilder $cwa): void
                {
                    $cwa->redirect('/old', to: 'about');
                    $cwa->redirect('/older', to: 'old');
                }
            PHP, $code);
        self::assertStringContainsString('The redirect from "/orphan" to "/nowhere", whose target has no page.', $this->display);
    }

    public function test_a_page_whose_route_redirects_redirects_to_its_target_by_route_name(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $home = $this->route('/', 'home');
        $graphs = $this->route('/graphs', 'graphs');
        $findings = $this->route('/graphs/top-line-findings', 'top-line-findings');
        $home->setRedirect($graphs);
        $graphs->setRedirect($findings);
        $this->page('home', $layout, $home)->setTitle('Home');
        $graphsPage = $this->page('graphs', $layout, $graphs);
        $graphsPage->setTitle('Graphs');
        $this->page('top-line-findings', $layout, $findings)->setParentPage($graphsPage);
        $this->repositories[Route::class] = [$home, $graphs, $findings];

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->page('home', uiComponent: 'Primary', layout: 'main', route: '/', routeName: 'home')\n            ->title('Home')\n            ->redirectTo(routeName: 'graphs');\n", $code);
        self::assertStringContainsString("->title('Graphs')\n            ->redirectTo(routeName: 'top-line-findings')\n            ->nested(\$this->topLineFindingsPage(...));\n", $code);
        self::assertStringNotContainsString('$cwa->redirect(', $code);
        self::assertStringNotContainsString('could not be reproduced', $this->display);
        $this->assertLints();
    }

    public function test_no_route_redirect_is_left_out_without_being_reported(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $aboutPage = $this->page('about', $layout, $about);
        $nowhere = $this->route('/nowhere', 'nowhere');
        $redirects = [];
        $redirect = static function (Route $route, Route $target) use (&$redirects): Route {
            $route->setRedirect($target);
            $redirects[] = $route;

            return $route;
        };
        $old = $redirect($this->route('/old', 'old'), $about);
        $redirect($this->route('/older', 'older'), $old);
        $orphan = $redirect($this->route('/orphan', 'orphan'), $nowhere);
        $redirect($this->route('/orphan-chain', 'orphan-chain'), $orphan);
        $this->page('home', $layout, $redirect($this->route('/', 'home'), $about));
        $this->page('legacy', $layout, $redirect($this->route('/legacy', 'legacy'), $old));
        $this->page('lost', $layout, $redirect($this->route('/lost', 'lost'), $nowhere));
        $this->page('stray', $layout, $redirect($this->route('/stray', 'stray'), $orphan));
        $this->page('child', $layout, $redirect($this->route('/about/child', 'child'), $about))->setParentPage($aboutPage);
        $event = new PageData();
        $event->setTitle('Event');
        $redirect($this->route('/event', 'event'), $about)->setPageData($event);
        $lostEvent = new PageData();
        $lostEvent->setTitle('Lost event');
        $redirect($this->route('/lost-event', 'lost-event'), $nowhere)->setPageData($lostEvent);
        $this->repositories[AbstractPageData::class] = [$event, $lostEvent];
        $first = $this->page('first-loop', $layout, $redirect($this->route('/loop-one', 'loop-one'), $about));
        $second = $this->page('second-loop', $layout, $redirect($this->route('/loop-two', 'loop-two'), $about));
        $first->setParentPage($second);
        $second->setParentPage($first);
        $this->repositories[Route::class] = [$about, $nowhere, ...$redirects];

        $code = $this->generate();

        foreach ($redirects as $route) {
            $path = $route->getPath();
            $carried = str_contains($code, \sprintf("\$cwa->redirect('%s', to: '%s'", $path, $route->getRedirect()->getName()))
                || 1 === preg_match(\sprintf("/route: '%s'[^;]*->redirectTo\\(routeName: '%s'\\);/s", preg_quote($path, '/'), preg_quote($route->getRedirect()->getName(), '/')), $code);
            $reported = str_contains($this->display, \sprintf('"%s"', $path)) || str_contains($this->display, '/_/pages/' . $route->getPage()?->reference . ' ');
            self::assertTrue($carried || $reported, \sprintf('The redirect from "%s" is neither carried nor reported.', $path));
        }
        $this->assertLints();
    }

    public function test_redirects_with_no_dependency_between_them_are_ordered_by_path(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $about = $this->route('/about', 'about');
        $this->page('about', $layout, $about);
        $zebra = $this->route('/zebra', 'zebra');
        $zebra->setRedirect($about);
        $apple = $this->route('/apple', 'apple');
        $apple->setRedirect($about);
        $this->repositories[Route::class] = [$zebra, $apple];

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->redirect('/apple', to: 'about');\n        \$cwa->redirect('/zebra', to: 'about');", $code);
    }

    public function test_a_redirect_name_is_written_only_when_the_builder_would_not_derive_it(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $about = $this->route('/about-us', 'about');
        $this->page('about', $layout, $about);
        $suffixed = $this->route('/about', 'about-1');
        $suffixed->setRedirect($about);
        $legacy = $this->route('/old', 'legacy');
        $legacy->setRedirect($about);
        $this->repositories[Route::class] = [$suffixed, $legacy];

        $code = $this->generate();

        self::assertStringContainsString("\$cwa->redirect('/about', to: 'about');", $code);
        self::assertStringContainsString("\$cwa->redirect('/old', to: 'about', name: 'legacy');", $code);
    }

    public function test_a_complete_site_generates_valid_php(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        $this->assertLints();
    }

    public function test_every_layout_and_top_level_page_data_is_emitted_and_children_only_from_their_parent(): void
    {
        $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $this->layout('secondary', uiComponent: 'CwaLayoutSecondary');
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

        self::assertStringContainsString("    private function mainLayout(CwaFixtureBuilder \$cwa): void\n    {\n        \$cwa->layout('main', uiComponent: 'Primary');\n    }\n", $code);
        self::assertStringContainsString("    private function secondaryLayout(CwaFixtureBuilder \$cwa): void\n    {\n        \$cwa->layout('secondary', uiComponent: 'Secondary');\n    }\n", $code);
        self::assertStringContainsString("\$pageData->setTitle('One');", $code);
        self::assertStringContainsString("\$pageData->setTitle('Two');", $code);
        self::assertStringContainsString('private function onePageData(', $code);
        self::assertStringContainsString('private function twoPageData(', $code);
        self::assertSame(3, substr_count($code, '$cwa->pageData($pageData'));
        self::assertStringContainsString("        \$cwa->page('first-child', uiComponent: 'Primary', layout: '', route: '/parent/first', routeName: 'first-child');", $code);
        self::assertStringContainsString("        \$cwa->page(\n            'second-child',\n            uiComponent: 'Primary',\n            layout: '',\n            route: '/parent/second',\n            routeName: 'second-child',\n        );\n", $code);
        self::assertSame(1, substr_count($code, "\$cwa->page('first-child'"));
        self::assertStringContainsString("        \$pageData->setTitle('Child data');\n\n        \$cwa->pageData(\$pageData)\n            ->withoutRoute();\n", $code);
        self::assertStringContainsString("            ->nested(\$this->firstChildPage(...))\n            ->nested(\$this->secondChildPage(...))\n            ->nested(\$this->childDataPageData(...));\n", $code);
        self::assertStringNotContainsString('$this->firstChildPage($cwa);' . "\n        \$this->", substr($code, 0, (int) strpos($code, 'private function')));
    }

    public function test_items_that_cannot_be_reproduced_are_listed_one_per_line(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/gone_ones', '/component/gone_twos'];

        $this->generate();

        self::assertStringContainsString("2 item(s) could not be reproduced:\n  - Allowed component \"/component/gone_ones\"", $this->display);
        self::assertStringContainsString("\n  - Allowed component \"/component/gone_twos\"", $this->display);
    }

    public function test_every_resolvable_allowed_class_is_kept_and_blank_node_iris_are_not_matched(): void
    {
        $this->blankNodeClasses = [DummyPublishableComponent::class];
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $group->allowedComponents = ['/component/gone_ones', '/component/dummy_components', '/component/dummy_navigation_links', '/.well-known/genid/' . md5(DummyPublishableComponent::class)];

        $code = $this->generate();

        self::assertStringContainsString("->group('top', allow: [DummyComponent::class, DummyNavigationLink::class])", $code);
        self::assertStringContainsString('use Silverback\\ApiComponentsBundle\\Tests\\Functional\\TestBundle\\Entity\\DummyNavigationLink;', $code);
        self::assertStringContainsString('does not match a component class', $this->display);
        self::assertStringContainsString('/.well-known/genid/', $this->display);
    }

    public function test_a_location_reference_keeps_everything_after_the_first_separator(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $this->group('nav_main_site', '/_/layouts/main', $layout);

        $code = $this->generate();

        self::assertStringContainsString("->group('nav', locationReference: 'main_site')", $code);
    }

    public function test_every_group_a_component_owns_is_emitted_without_reporting_them(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertStringContainsString('$cwa->component($mainTopDummyComponent)', $code);
        self::assertStringContainsString("->group('left')", $code);
        self::assertStringContainsString("->group('right')", $code);
        self::assertLessThan(strpos($code, "->group('right')"), strpos($code, "->group('left')"));
        self::assertStringNotContainsString('could not be reproduced', $this->display);
    }

    public function test_a_component_is_constructed_before_its_fields_and_an_unwritable_value_does_not_stop_later_fields(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $link = new DummyNavigationLink();
        $link->uiClassNames = [new \stdClass()];
        $link->label = 'After';
        (new \ReflectionProperty(AbstractComponent::class, 'id'))->setValue($link, \Ramsey\Uuid\Uuid::uuid4());
        $this->position($group, $link, 10);
        $unchanged = new DummyPublishableComponent();
        $this->position($group, $unchanged, 20);

        $code = $this->generate();

        self::assertLessThan(strpos($code, "\$afterDummyNavigationLink->label = 'After';"), strpos($code, '$afterDummyNavigationLink = new DummyNavigationLink();'));
        self::assertStringNotContainsString('$id', $this->display);
        self::assertStringNotContainsString('->id', $code);
        self::assertStringContainsString("            ->add(\$afterDummyNavigationLink)\n            ->add(new DummyPublishableComponent());", $code);
    }

    public function test_an_uploaded_file_is_written_alongside_the_other_fields(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->filesystem->write('components/photo.jpg', 'photo');
        $component = new DummyUploadableAndPublishable();
        $component->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $component->setFilename('components/photo.jpg');
        $this->position($group, $component, 10);

        $code = $this->generate();

        self::assertStringContainsString("\$mainTopDummyUploadableAndPublishable->file = new File(__DIR__ . '/assets/photo.jpg');\n        \$cwa->publish(\$mainTopDummyUploadableAndPublishable);\n", $code);
    }

    public function test_page_data_route_is_not_reported_and_a_scheduled_page_keeps_its_title(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $template = $this->page('template', $layout);
        $template->isTemplate = true;
        $pageData = new PageDataWithComponent();
        $pageData->setTitle('Conference');
        $pageData->page = $template;
        $this->route('/conference', 'conference')->setPageData($pageData);
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
        self::assertStringContainsString("\$cwa->page('soon', uiComponent: 'Primary', layout: 'main', route: '/soon')\n            ->title('Soon')\n            ->liveAt(new \\DateTimeImmutable('2999-01-01T00:00:00+00:00'));\n", $code);
        self::assertStringContainsString("\$cwa->pageData(\$pageData, template: 'template', route: '/conference')", $code);
        self::assertStringContainsString('->liveAt(null)', $code);
        self::assertStringContainsString("->liveAt(null)\n            ->nested(\$this->programmePage(...));\n", $code);
        self::assertStringContainsString("    private function programmePage(CwaFixtureBuilder \$cwa): void\n    {\n        \$cwa->page(\n            'programme',\n            uiComponent: 'Primary',\n            layout: 'main',\n            route: '/conference/programme',\n            routeName: 'programme',\n        );\n", $code);
        self::assertLessThan(strpos($code, 'private function programmePage('), strpos($code, '->nested($this->programmePage(...))'));
    }

    public function test_every_draft_and_reference_is_emitted(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
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

        self::assertSame(2, substr_count($code, '->draft('));
        self::assertSame(2, substr_count($code, "->linkRoute('route', routeName: 'about');"));
        self::assertStringContainsString("\$oneDummyPublishableComponentDraft = new DummyPublishableComponent();\n        \$oneDummyPublishableComponentDraft->reference = 'one-draft';", $code);
        self::assertStringContainsString("\$twoDummyPublishableComponentDraft->reference = 'two-draft';", $code);
        self::assertStringNotContainsString('->persist(', $code);
    }

    public function test_use_statements_are_sorted(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $group = $this->group('top_/_/layouts/main', '/_/layouts/main', $layout);
        $this->position($group, new DummyNavigationLink(), 10);
        $this->position($group, new DummyComponent(), 20);

        $code = $this->generate();

        self::assertLessThan(strpos($code, 'Entity\\DummyNavigationLink;'), strpos($code, 'Entity\\DummyComponent;'));
    }

    private function documentedSite(): void
    {
        $layout = $this->layout('main', uiComponent: 'CwaLayoutPrimary');
        $homeRoute = $this->route('/', 'home');
        $aboutRoute = $this->route('/about', 'about');
        $teamRoute = $this->route('/about/team', 'team');
        $conferenceRoute = $this->route('/cwa-conference', 'cwa-conference');
        $conferenceRoute->setLiveAt(new \DateTimeImmutable('2099-01-15T09:00:00+00:00'));
        $conferenceRedirect = $this->route('/conference', 'conference');
        $conferenceRedirect->setRedirect($conferenceRoute);
        $teamRedirect = $this->route('/our-team', 'our-team');
        $teamRedirect->setRedirect($teamRoute);
        $eventsRoute = $this->route('/events', 'events');
        $eventsRoute->setRedirect($conferenceRoute);
        $this->repositories[Route::class] = [$teamRedirect, $homeRoute, $conferenceRedirect, $aboutRoute, $eventsRoute, $teamRoute, $conferenceRoute];

        $header = $this->group('header_/_/layouts/main', '/_/layouts/main', $layout);
        $header->allowedComponents = ['/component/dummy_navigation_links'];
        $header->maxComponents = 5;
        $contactUs = null;
        foreach ([['Home', $homeRoute, null, null], ['About', $aboutRoute, null, null], ['Our team', $teamRoute, 'subtle', null], ['Conference', $conferenceRoute, null, '2099-03-01T09:00:00+00:00'], ['Contact us', $aboutRoute, null, null]] as $sort => [$label, $route, $theme, $eventDate]) {
            $link = new DummyNavigationLink();
            $link->label = $label;
            $link->route = $route;
            $link->theme = $theme;
            $link->eventDate = null === $eventDate ? null : new \DateTimeImmutable($eventDate);
            $this->position($header, $link, ($sort + 1) * 10);
            $contactUs = $link;
        }

        $home = $this->page('home', $layout);
        $homeRoute->setPage($home);
        $home->setTitle('Home');
        $home->setMetaDescription('Component-driven sites with CWA');
        $this->filesystem->write('components/home-hero-0a1b2c3d.png', (string) file_get_contents(\dirname(self::DOCUMENTED_SCAFFOLD) . '/assets/home-hero.png'));
        $hero = new DummyUploadableAndPublishable();
        $hero->setFilename('components/home-hero-0a1b2c3d.png');
        $hero->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $this->position($this->group('hero_/_/pages/home', '/_/pages/home', $home), $hero, 10);
        $primary = $this->group('primary_/_/pages/home', '/_/pages/home', $home);
        $welcome = new DummyPublishableComponent();
        $welcome->reference = 'Welcome to CWA';
        $welcome->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $welcomeDraft = new DummyPublishableComponent();
        $welcomeDraft->reference = 'Welcome to CWA 2.0';
        $welcomeDraft->setPublishedResource($welcome);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($welcome, $welcomeDraft);
        $this->position($primary, $welcome, 10);
        $intro = new DummyHtmlContent();
        $intro->html = '<h2>Build pages from components</h2>'
            . '<p>CWA gives every page a layout, groups and positions, so editors can rearrange content without a deploy.</p>'
            . '<p>Each component is fetched and cached on its own, which keeps a content edit from purging every page that shows it.</p>'
            . '<ul><li>Layouts and pages</li><li>Reusable components</li><li>Scheduled publishing</li></ul>';
        $this->position($primary, $intro, 20);
        $gettingStarted = new DummyHtmlContent();
        $gettingStarted->html = "<h3>Getting started</h3><p>Install the bundle, then generate a scaffold from your site:</p><pre><code>composer require silverback/api-components-bundle\nphp bin/console silverback:api-components:generate-fixtures --output=src/DataFixtures/SiteScaffold.php</code></pre>";
        $this->position($primary, $gettingStarted, 30);
        $tickets = new DummyPublishableComponent();
        $tickets->reference = 'Tickets on sale';
        $tickets->setPublishedAt(new \DateTime('2099-01-15T09:00:00+00:00'));
        $this->position($primary, $tickets, 40);
        $this->position($primary, new DummyComponent(), 50);

        $about = $this->page('about', $layout);
        $aboutRoute->setPage($about);
        $about->setTitle('About');
        $about->setMetaDescription('Who builds CWA');
        $aboutText = new DummyHtmlContent();
        $aboutText->html = '<p>We started CWA to stop rebuilding the same CMS for every client.</p><p>It is open source and maintained by Silverback.</p>';
        $this->position($this->group('primary_/_/pages/about', '/_/pages/about', $about), $aboutText, 10);

        $team = $this->page('about-team', $layout);
        $teamRoute->setPage($team);
        $team->setTitle('Team');
        $team->setParentPage($about);
        $teamGroup = $this->group('primary_/_/pages/about-team', '/_/pages/about-team', $team);
        $teamText = new DummyHtmlContent();
        $teamText->html = '<p>A small team of developers and designers.</p>';
        $this->position($teamGroup, $teamText, 10);
        $this->position($teamGroup, $contactUs, 20);

        $this->page('events', $layout, $eventsRoute)->setTitle('Events');

        $template = $this->page('conference', $layout);
        $template->isTemplate = true;
        $template->setTitle('Conference');
        $templateGroup = $this->group('primary_/_/pages/conference', '/_/pages/conference', $template);
        $templateGroup->allowedComponents = ['/component/dummy_components'];
        $pageDataPosition = new ComponentPosition();
        $pageDataPosition->sortValue = 10;
        $pageDataPosition->pageDataClass = PageDataWithComponent::class;
        $pageDataPosition->pageDataProperty = 'component';
        $pageDataPosition->componentGroup = $templateGroup;
        $templateGroup->componentPositions->add($pageDataPosition);

        $pageData = new PageDataWithComponent();
        $pageData->setTitle('CWA Conference');
        $pageData->setMetaDescription('Two days of components in London');
        $pageData->page = $template;
        $pageData->component = new DummyComponent();
        $conferenceRoute->setPageData($pageData);
        $this->repositories[AbstractPageData::class][] = $pageData;
    }

    private function labelled(string $uiComponent): DummyComponent
    {
        $component = new DummyComponent();
        $component->uiComponent = $uiComponent;

        return $component;
    }

    private function assertLints(): void
    {
        exec(\sprintf('%s -l %s 2>&1', escapeshellarg(\PHP_BINARY), escapeshellarg($this->outputFile)), $lint, $status);
        self::assertSame(0, $status, implode("\n", $lint) . "\n" . file_get_contents($this->outputFile));
    }

    private function generate(array $options = []): string
    {
        $tester = $this->runCommand($options);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->display = $tester->getDisplay();

        return file_get_contents($options['--output'] ?? $this->outputFile);
    }

    private function runCommand(array $options = []): CommandTester
    {
        $registry = $this->registry();

        $filesystems = new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->filesystem]));
        $command = new GenerateFixturesCommand(new ScaffoldGenerator(
            $registry,
            $this->iriConverter(),
            new UploadableAttributeReader($registry, true),
            new PublishableAttributeReader($registry),
            new TimestampedAttributeReader($registry),
            $filesystems,
        ), $this->assetStores ?? new FixtureAssetStoreFactory($filesystems));

        $tester = new CommandTester($command);
        $tester->execute($options + ['--output' => $this->outputFile]);

        return $tester;
    }
}
