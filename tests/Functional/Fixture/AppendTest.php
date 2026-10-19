<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use Monolog\Level;
use Monolog\LogRecord;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;

final class AppendTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;
    use LogRecordsTrait;

    public function test_a_load_logs_each_created_entity_at_debug_each_kept_or_skipped_one_at_notice_and_counts_them_in_singular_and_plural(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->scaffoldHasAPage('about', 'main', '/about', 'primary', 'About text, Contact');
        $this->loadScaffold(self::getContainer()->get('logger'));

        $this->assertLastLoadSummary('CWA scaffold: created 2 pages, 1 layout, 2 routes, 3 groups, 4 components');
        self::assertLogRecord(Level::Debug, 'created page `home`');
        self::assertLogRecord(Level::Debug, 'created layout `main`');

        $this->loadScaffold(self::getContainer()->get('logger'));

        $this->assertLastLoadSummary('CWA scaffold: kept 2 pages, 1 layout, 3 groups; skipped 4 positions (group kept)');
        self::assertLogRecord(Level::Notice, 'kept page `home`');
        self::assertLogRecord(Level::Notice, 'kept layout `main`');
        self::assertLogRecord(Level::Notice, 'The group `primary` of the page `about` already exists, so its 2 scaffold positions were not created.');
    }

    private static function assertLogRecord(Level $level, string $message): void
    {
        self::assertLogged(\sprintf('%s %s', $level->getName(), $message), static fn (LogRecord $record): bool => $level === $record->level && $message === $record->message);
    }

    public function test_loading_the_same_scaffold_again_changes_nothing(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero, Text');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasAPageDataAtTheRoute('Conference', '/conference', 'template');
        $this->loadScaffold();
        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummary('CWA scaffold: kept 2 pages, 1 page data, 1 layout, 2 groups; skipped 3 positions (group kept)');
    }

    public function test_a_page_added_to_the_scaffold_is_created_and_an_editors_change_to_existing_content_survives(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->loadScaffold();
        $this->editorRenamesTheComponent('Hero', 'Edited hero');
        $this->recordDatabaseContents();
        $this->scaffoldHasAPage('about', 'main', '/about', 'primary', 'About text');
        $this->loadScaffold();

        $this->assertDatabaseGainedOnly(['Page' => 1, 'Route' => 1, 'ComponentGroup' => 1, 'ComponentPosition' => 1, 'Component' => 1]);
        $this->assertComponentsLabelled(1, 'Edited hero');
        $this->assertComponentsLabelled(0, 'Hero');
        $this->assertLastLoadSummary('CWA scaffold: created 1 page, 1 route, 1 group, 1 component; kept 1 page, 1 layout, 2 groups; skipped 2 positions (group kept)');
    }

    public function test_a_new_page_that_uses_an_existing_layout_is_attached_to_it(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->loadScaffold();
        $this->scaffoldHasAPage('about', 'main', '/about', 'primary', 'About text');
        $this->loadScaffold();

        $this->assertLayoutsWithTheReference(1, 'main');
        $this->assertPageUsesTheLayout('about', 'main');
    }

    public function test_a_new_page_whose_route_path_belongs_to_another_page_is_created_without_a_route(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('about', 'main', '/about', 'primary', 'About text');
        $this->loadScaffold();
        $this->scaffoldHasAPage('contact', 'main', '/about', 'primary', 'Contact form');
        $this->loadScaffold();

        $this->assertPageHasNoRoute('contact');
        $this->assertRouteBelongsToThePage('/about', 'about');
        $this->assertLastLoadSummaryContains('skipped 1 route (path in use)');
    }

    public function test_a_group_added_to_an_existing_page_is_created_and_a_component_added_to_an_existing_group_is_not(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->loadScaffold();
        $this->scaffoldAddsComponentsToTheGroupOfThePage('Extra', 'primary', 'home');
        $this->scaffoldAddsComponentsToTheGroupOfThePage('Aside', 'sidebar', 'home');
        $this->loadScaffold();

        $this->assertGroupOfThePageHoldsTheComponents('sidebar', 'home', 'Aside');
        $this->assertGroupOfThePageHoldsTheComponents('primary', 'home', 'Hero');
        $this->assertComponentsLabelled(0, 'Extra');
        $this->assertLastLoadSummary('CWA scaffold: created 1 group, 1 component; kept 1 page, 1 layout, 2 groups; skipped 3 positions (group kept)');
    }

    public function test_a_nested_page_whose_component_owns_an_existing_group_is_kept_and_the_group_stays_linked_to_the_component(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('parent', 'main', '/parent', 'primary', 'Hero');
        $this->scaffoldHasANestedPageWithAComponentOwningALocatedGroup('parent', 'child', '/parent/child', 'main', 'Tabs', 'panels', 'shared-panels', 'Panel');
        $this->loadScaffold();

        $this->assertComponentGroupBelongsToTheComponents('panels_shared-panels', 'Tabs');

        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertComponentOwnsAGroupHoldingTheComponent('Tabs', 'panels', 'Panel');
        $this->assertLastLoadSummary('CWA scaffold: kept 2 pages, 1 layout, 3 groups; skipped 3 positions (group kept)');
    }

    public function test_a_component_declared_in_a_nested_closure_is_saved_with_its_link_to_an_existing_group_when_nothing_else_in_the_load_is_new(): void
    {
        $this->scaffoldHasALayoutWithALocatedGroup('main', 'panels', 'shared-panels', 'Panel');
        $this->scaffoldHasAPage('parent', 'main', '/parent', 'primary', 'Hero');
        $this->loadScaffold();
        $this->recordDatabaseContents();
        $this->scaffoldHasANestedComponentOwningALocatedGroup('parent', 'Accordion', 'panels', 'shared-panels', 'Section');
        $this->loadScaffold();

        $this->assertDatabaseGainedOnly(['Component' => 1]);
        $this->assertComponentGroupBelongsToTheComponents('panels_shared-panels', 'Accordion');
        $this->assertComponentsLabelled(0, 'Section');
        $this->assertLastLoadSummary('CWA scaffold: created 1 component; kept 1 page, 1 layout, 2 groups; skipped 3 positions (group kept)');
    }

    public function test_page_data_with_a_generated_route_is_kept_rather_than_given_a_suffixed_route(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasAPageDataAtTheRoute('Conference', '/conference', 'template');
        $this->scaffoldHasANestedPageData('Programme', 'Conference', 'template');
        $this->loadScaffold();
        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertPageDataTitled(1, 'Programme');
        $this->assertRouteExists('/conference/programme');
        $this->assertLastLoadSummary('CWA scaffold: kept 1 page, 2 page data, 1 layout, 1 group; skipped 1 position (group kept)');
    }

    public function test_page_data_with_no_route_is_created_with_its_template_and_skipped_once_the_template_exists(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasATemplatePage('template', 'main');
        $this->scaffoldHasARoutelessPageData('Draft', 'template');
        $this->loadScaffold();

        $this->assertPageDataWithNoRoute(1);

        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummaryContains('skipped 1 page data (unidentifiable)');
    }

    public function test_a_generated_scaffold_loaded_without_purging_duplicates_and_modifies_nothing(): void
    {
        $this->siteHasAPublishedComponentWithADraft('live', 'live-draft');
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->siteHasAComponentWhoseOwnGroupHoldsAComponent('Tabs', 'panels', 'Panel');
        $this->siteHasANavigationLink('/about', '2027-01-01T10:00:00+00:00', 'dark', 'check');
        $this->siteHasAPageDataWithComponent('Conference', 'Intro');
        $this->siteHasARedirect('/old-about', '/about');
        $this->generateSite();
        $this->recordDatabaseContents();
        $this->loadGeneratedFixturesWithoutPurging();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummaryNotContains('created');
    }

    public function test_a_generated_scaffold_whose_shared_component_with_its_own_group_is_first_used_under_skipped_page_data_loads_without_purging(): void
    {
        $this->siteHasAComponentWithAnOwnGroupUnderRoutelessPageDataAndAPage('Tabs', 'panels', 'Panel', 'parent');
        $this->generateSite();
        $this->recordDatabaseContents();
        $this->loadGeneratedFixturesWithoutPurging();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummaryContains('skipped 1 page data (unidentifiable)');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertComponentOwnsAGroupHoldingTheComponent('Tabs', 'panels', 'Panel');
    }

    public function test_a_redirect_whose_derived_name_belongs_to_a_route_created_in_the_same_load_gets_a_unique_name(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('about', 'main', '/about-us', 'primary', 'Text');
        $this->scaffoldHasARedirect('/about', 'about');
        $this->loadScaffold();

        self::assertRouteRedirectsTo('/about', '/about-us');
        $this->assertRouteIsNamed('/about-us', 'about');
        $this->assertRouteIsNamed('/about', 'about-1');

        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
    }

    public function test_a_redirect_whose_derived_name_belongs_to_an_existing_route_gets_a_unique_name(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('about', 'main', '/about-us', 'primary', 'Text');
        $this->loadScaffold();
        $this->scaffoldHasARedirect('/about', 'about');
        $this->loadScaffold();

        self::assertRouteRedirectsTo('/about', '/about-us');
        $this->assertRouteIsNamed('/about', 'about-1');
    }

    public function test_doctrine_fixtures_load_prints_the_summary_at_default_verbosity(): void
    {
        $this->runCommandLine('doctrine:fixtures:load', 'cwa_append');

        $this->assertCommandOutputContains('CWA scaffold: created');

        $this->runCommandLine('doctrine:fixtures:load', 'cwa_append');

        $this->assertCommandOutputContains('CWA scaffold: kept');
        $this->assertCommandOutputNotContains('created');
    }

    public function test_two_scaffolds_in_one_doctrine_fixtures_load_share_a_layout_through_the_database_not_through_the_builder(): void
    {
        $this->runCommandLine('doctrine:fixtures:load', 'cwa_two_scaffolds');

        $this->assertLayoutsWithTheReference(1, 'shared-main');
        $this->assertPageUsesTheLayout('shared-home', 'shared-main');
        $this->assertPageUsesTheLayout('shared-about', 'shared-main');
        $this->assertGroupOfThePageHoldsTheComponents('primary', 'shared-home', 'Hero');
        $this->assertGroupOfThePageHoldsTheComponents('primary', 'shared-about', 'About text');
        self::assertRouteRedirectsTo('/shared-old', '/shared-home');
        $this->assertComponentsLabelled(1, 'Logo');
        $this->assertCommandOutputContains('CWA scaffold: created 1 page, 1 layout, 1 route, 2 groups, 2 components');
        $this->assertCommandOutputContains('CWA scaffold: created 1 page, 2 routes, 1 group, 1 component; kept 1 layout');
    }

    public function test_a_draft_whose_published_component_sits_in_a_kept_nested_group_is_skipped_not_duplicated(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('parent', 'main', '/parent', 'primary', 'Hero');
        $this->scaffoldHasANestedPageWithAPublishedComponentAndADraft('parent', 'child', '/parent/child', 'main', 'card', 'card-draft');
        $this->loadScaffold();

        $this->assertComponentIsPublishedAndPlaced('card');
        $this->assertComponentIsAnUnpublishedDraftOf('card-draft', 'card');

        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        $this->assertLastLoadSummaryContains('(linked to skipped content)');
    }

    public function test_a_route_link_on_a_component_in_a_kept_group_neither_throws_nor_writes(): void
    {
        $this->scaffoldHasALayoutWithANavigationLink('main', 'nav', 'Home', 'home');
        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->loadScaffold();

        $this->assertNavigationLinkLabelledPointsAtTheRoute('Home', '/');

        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
    }

    public function test_a_group_owning_component_shared_by_two_nested_pages_is_not_duplicated_when_one_pages_group_is_kept(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('parent', 'main', '/parent', 'primary', 'Hero');
        $this->scaffoldHasANestedPageHoldingASharedComponent('parent', 'one', '/parent/one', 'main', 'Tabs', 'panels', 'Panel');
        $this->loadScaffold();
        $this->recordDatabaseContents();
        $this->scaffoldHasANestedPageHoldingASharedComponent('parent', 'two', '/parent/two', 'main', 'Tabs', 'panels', 'Panel');
        $this->loadScaffold();

        $this->assertDatabaseGainedOnly(['Page' => 1, 'Route' => 1, 'ComponentGroup' => 1]);
        $this->assertComponentsLabelled(1, 'Tabs');
        $this->assertComponentsLabelled(1, 'Panel');
    }

    public function test_a_pages_route_redirect_is_created_with_the_page_and_a_redirect_added_later_leaves_the_existing_route_alone(): void
    {
        $this->scaffoldHasALayoutWithAGroup('main', 'top', 'Logo');
        $this->scaffoldHasAPage('graphs', 'main', '/graphs', 'primary', 'Chart');
        $this->scaffoldHasAPage('resources', 'main', '/resources', 'primary', 'List');
        $this->scaffoldsPageRedirectsTo('resources', 'graphs');
        $this->loadScaffold();

        self::assertRouteRedirectsTo('/resources', '/graphs');

        $this->scaffoldHasAPage('home', 'main', '/', 'primary', 'Hero');
        $this->loadScaffold();
        $this->scaffoldsPageRedirectsTo('graphs', 'home');
        $this->recordDatabaseContents();
        $this->loadScaffold();

        $this->assertDatabaseContentsUnchanged();
        self::assertRouteDoesNotRedirect('/graphs');
        self::assertRouteRedirectsTo('/resources', '/graphs');
    }
}
