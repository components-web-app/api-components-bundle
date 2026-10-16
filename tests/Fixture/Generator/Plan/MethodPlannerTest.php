<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlanner;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\NotReproducedReport;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\SiteSnapshot;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;

class MethodPlannerTest extends TestCase
{
    use PlannerTestTrait;

    private const string NOW = '2050-01-01T00:00:00+00:00';

    private NotReproducedReport $report;
    private ImportCollector $imports;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function test_a_layout_is_written_with_its_ui_component_suffix_and_class_names(): void
    {
        $prefixed = $this->layout('main', 'CwaLayoutPrimary');
        $prefixed->uiClassNames = ['dark'];
        $bare = $this->layout('plain', 'Secondary');
        $none = $this->layout('none', 'x');
        $none->uiComponent = null;

        $methods = $this->methods(new SiteSnapshot(layouts: [$prefixed, $bare, $none]));

        self::assertSame(['mainLayout', 'plainLayout', 'noneLayout'], array_keys($this->methodsByName($methods)));
        self::assertSame(MethodKind::Layout, $methods[0]->kind);
        self::assertSame($prefixed, $methods[0]->entity);
        self::assertSame(["'main'", "uiComponent: 'Primary'", "uiClassNames: ['dark']"], $methods[0]->arguments);
        self::assertSame(["'plain'", "uiComponent: 'Secondary'"], $methods[1]->arguments);
        self::assertSame(["'none'", "uiComponent: ''"], $methods[2]->arguments);
    }

    public function test_a_route_name_equal_to_the_derived_name_is_omitted(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $about = $this->page('about', $layout, $this->route('/about/us', 'about-us'));
        $home = $this->page('home', $layout, $this->route('/', 'home'));

        $methods = $this->methods(new SiteSnapshot(pages: [$about, $home]));

        self::assertSame(["'about'", "uiComponent: 'Primary'", "layout: 'main'", "route: '/about/us'"], $methods[0]->arguments);
        self::assertSame(["'home'", "uiComponent: 'Primary'", "layout: 'main'", "route: '/'", "routeName: 'home'"], $methods[1]->arguments);
    }

    public function test_a_page_writes_its_template_flag_class_names_title_and_description(): void
    {
        $template = $this->page('article', null);
        $template->isTemplate = true;
        $template->uiClassNames = ['wide'];
        $template->setTitle('Article');
        $template->setMetaDescription('An article');

        $method = $this->methods(new SiteSnapshot(pages: [$template]))[0];

        self::assertSame(["'article'", "uiComponent: 'Primary'", "layout: ''", 'isTemplate: true', "uiClassNames: ['wide']"], $method->arguments);
        self::assertSame(["title('Article')", "metaDescription('An article')"], $method->calls);
    }

    public function test_a_page_reachable_without_a_route_says_so(): void
    {
        $page = $this->page('hidden', null);
        $page->isReachableWithoutRoute = true;

        self::assertSame(['withoutRoute()', 'reachableWithoutRoute()'], $this->methods(new SiteSnapshot(pages: [$page]))[0]->calls);
    }

    public function test_only_a_go_live_date_that_is_not_past_is_written_unless_timestamps_are_kept(): void
    {
        $past = $this->route('/past', 'past');
        $past->setLiveAt(new \DateTimeImmutable('2001-02-03T04:05:06+00:00'));
        $now = $this->route('/now', 'now');
        $now->setLiveAt(new \DateTimeImmutable(self::NOW));
        $future = $this->route('/future', 'future');
        $future->setLiveAt(new \DateTimeImmutable('2050-01-01T00:00:01+00:00'));
        $draft = $this->route('/draft', 'draft');
        $draft->setLiveAt(null);
        $site = new SiteSnapshot(pages: [$this->page('past', null, $past), $this->page('now', null, $now), $this->page('future', null, $future), $this->page('draft', null, $draft)]);

        $calls = array_map(static fn (MethodPlan $method): array => $method->calls, $this->methods($site));
        $kept = array_map(static fn (MethodPlan $method): array => $method->calls, $this->methods($site, keepTimestamps: true));

        self::assertSame([[], [], ["liveAt(new \\DateTimeImmutable('2050-01-01T00:00:01+00:00'))"], ['liveAt(null)']], $calls);
        self::assertSame(["liveAt(new \\DateTimeImmutable('2001-02-03T04:05:06+00:00'))"], $kept[0]);
        self::assertSame(["liveAt(new \\DateTimeImmutable('2050-01-01T00:00:00+00:00'))"], $kept[1]);
    }

    public function test_page_data_is_passed_as_its_imported_class_with_its_template_and_route(): void
    {
        $event = new PageData();
        $event->setTitle('Event');
        $event->page = $this->page('article', null);
        $this->route('/event', 'event')->setPageData($event);
        $loose = new PageData();
        $loose->setTitle('Loose');

        $methods = $this->methods(new SiteSnapshot(pageData: [$event, $loose]));

        self::assertSame(['eventPageData', 'loosePageData'], [$methods[0]->name, $methods[1]->name]);
        self::assertSame(MethodKind::PageData, $methods[0]->kind);
        self::assertSame('PageData', $methods[0]->entityClass);
        self::assertContains('use ' . PageData::class . ';', $this->imports->lines());
        self::assertSame(['$pageData', "template: 'article'", "route: '/event'"], $methods[0]->arguments);
        self::assertSame([], $methods[0]->calls);
        self::assertSame(['$pageData'], $methods[1]->arguments);
        self::assertSame(['withoutRoute()'], $methods[1]->calls);
    }

    public function test_children_are_nested_methods_of_their_parent_in_site_order(): void
    {
        $layout = $this->layout('main', 'CwaLayoutPrimary');
        $parent = $this->page('parent', $layout, $this->route('/parent', 'parent'));
        $child = $this->page('child', $layout, $this->route('/parent/child', 'child'));
        $childData = new PageData();
        $childData->setTitle('Event');
        $grandchild = $this->page('grandchild', $layout, $this->route('/parent/event/grandchild', 'grandchild'));
        $site = new SiteSnapshot(pages: [$parent], children: [
            spl_object_id($parent) => [$child, $childData],
            spl_object_id($childData) => [$grandchild],
        ]);

        $methods = $this->methods($site);

        self::assertCount(1, $methods);
        self::assertSame(['parentPage', 'childPage', 'eventPageData', 'grandchildPage'], array_keys($this->methodsByName($methods)));
        self::assertSame(MethodKind::PageData, $methods[0]->children[1]->kind);
    }

    public function test_a_redirect_name_is_omitted_only_when_the_builder_derives_the_same_name(): void
    {
        $about = $this->route('/about', 'about');
        $suffixed = $this->route('/about/', 'about-1');
        $suffixed->setRedirect($about);
        $named = $this->route('/old', 'legacy');
        $named->setRedirect($about);
        $derived = $this->route('/older', 'older');
        $derived->setRedirect($named);
        $taken = $this->route('/legacy', 'legacy-1');
        $taken->setRedirect($about);

        $methods = $this->methods(new SiteSnapshot(routes: [$about], redirects: [$suffixed, $named, $derived, $taken]));

        self::assertSame(MethodKind::Redirects, $methods[0]->kind);
        self::assertSame('redirects', $methods[0]->name);
        self::assertSame([
            ["'/about/'", "to: 'about'"],
            ["'/old'", "to: 'about'", "name: 'legacy'"],
            ["'/older'", "to: 'legacy'"],
            ["'/legacy'", "to: 'about'"],
        ], $methods[0]->redirects);
    }

    public function test_a_site_without_redirects_has_no_redirects_method(): void
    {
        self::assertSame([], $this->methods(new SiteSnapshot(routes: [$this->route('/', 'home')])));
    }

    public function test_redirects_that_are_not_live_unresolved_or_pages_never_reached_are_reported_in_that_order(): void
    {
        $about = $this->route('/about', 'about');
        $draft = $this->route('/old', 'old');
        $draft->setRedirect($about);
        $draft->setLiveAt(null);
        $scheduled = $this->route('/older', 'older');
        $scheduled->setRedirect($about);
        $scheduled->setLiveAt(new \DateTimeImmutable('2099-01-01T00:00:00+00:00'));
        $live = $this->route('/oldest', 'oldest');
        $live->setRedirect($about);
        $current = $this->route('/current', 'current');
        $current->setRedirect($about);
        $current->setLiveAt(new \DateTimeImmutable(self::NOW));
        $unresolved = $this->route('/orphan', 'orphan');
        $unresolved->setRedirect($this->route('/nowhere', 'nowhere'));
        $unreached = new Page();
        $unreached->reference = 'loop';

        $this->methods(new SiteSnapshot(routes: [$about], redirects: [$draft, $scheduled, $live, $current], unresolvedRedirects: [$unresolved], unreached: [$unreached]));

        self::assertSame([
            'The redirect from "/old" goes live at no date (a draft), but a fixture redirect is always live.',
            'The redirect from "/older" goes live at 2099-01-01T00:00:00+00:00, but a fixture redirect is always live.',
            'The redirect from "/orphan" to "/nowhere", whose target has no page.',
            '/_/pages/loop is nested under a page that is itself nested under it, so it has no top-level page to be built from.',
        ], $this->report->all());
    }

    /**
     * @return list<MethodPlan>
     */
    private function methods(SiteSnapshot $site, bool $keepTimestamps = false): array
    {
        $this->report = new NotReproducedReport($this->iriConverter());
        $this->imports = new ImportCollector('SiteScaffold');

        return (new MethodPlanner($site, new VariableNamer(), $this->stateReader($this->registry()), $this->imports, new PhpValueExporter(), $this->report, new \DateTimeImmutable(self::NOW), $keepTimestamps))->plan();
    }
}
