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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class GenerateFixturesRoundTripTest extends FunctionalTestCase
{
    use FixtureScenarioTrait;

    public function test_a_group_with_allowed_components_and_positions_generates_valid_php_and_keeps_its_allowed_components(): void
    {
        $this->siteHasGroupsThatAllowAndHoldADummyComponent();
        $this->generateSite();

        $this->assertGeneratedFixturesAreValidPhp();

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertGroupOfTheLayoutAllowsOnly('top', 'main', 'DummyComponent', 1);
        $this->assertGroupOfThePageAllowsOnly('primary', 'home', 'DummyComponent', 1);
    }

    public function test_a_group_keeps_its_maximum_number_of_components(): void
    {
        $this->siteHasGroupsWithAndWithoutAMaximum(2);
        $this->generateSiteAndReload();

        $this->assertGroupOfTheLayoutHoldsAtMost('top', 'main', 2);
        $this->assertGroupOfThePageHasNoMaximum('primary', 'home');
        $this->assertGeneratedFixturesContain('max: 2');
        $this->assertCommandOutputNotContains('could not be reproduced');
    }

    public function test_published_components_stay_published_and_a_draft_stays_linked_to_its_published_component(): void
    {
        $this->siteHasAPublishedComponentWithADraft('live', 'live-draft');
        $this->generateSiteAndReload();

        $this->assertComponentIsPublishedAndPlaced('live');
        $this->assertComponentIsAnUnpublishedDraftOf('live-draft', 'live');
        $this->assertGeneratedFixturesContain('->draft(');
        $this->assertGeneratedFixturesNotContain('afterRoutes(');
    }

    public function test_a_draft_added_to_a_group_is_placed_as_its_published_component_and_comes_back_so(): void
    {
        $this->siteHasAPublishedComponentWithAPlacedDraft('live', 'pending');
        $this->siteIsFlushed();

        $this->assertComponentHoldsPositions('live', 2);
        $this->assertComponentHoldsPositions('pending', 0);
        $this->assertComponentIsAnUnpublishedDraftOf('pending', 'live');

        $this->generateSiteAndReload();

        $this->assertComponentHoldsPositions('live', 2);
        $this->assertComponentHoldsPositions('pending', 0);
        $this->assertComponentIsAnUnpublishedDraftOf('pending', 'live');
        $this->assertGeneratedFixturesContain('->draft(');
        $this->assertCommandOutputNotContains('could not be reproduced');
    }

    public function test_a_draft_added_to_a_group_without_its_published_component_places_the_published_component(): void
    {
        $this->siteHasAnUnplacedPublishedComponentWithAPlacedDraft('live', 'pending');
        $this->siteIsFlushed();

        $this->assertComponentHoldsPositions('live', 1);
        $this->assertComponentHoldsPositions('pending', 0);

        $this->generateSiteAndReload();

        $this->assertComponentIsPublishedAndPlaced('live');
        $this->assertComponentHoldsPositions('pending', 0);
        $this->assertComponentIsAnUnpublishedDraftOf('pending', 'live');
        $this->assertCommandOutputNotContains('could not be reproduced');
    }

    public function test_a_draft_given_to_a_page_data_property_holds_its_published_component_and_comes_back_so(): void
    {
        $this->siteHasAPageDataHoldingADraftOfAPublishedComponent('Conference', 'live', 'pending');
        $this->siteIsFlushed();

        $this->assertPageDataWithComponentHoldsThePublishableComponent('Conference', 'live');
        $this->assertComponentIsAnUnpublishedDraftOf('pending', 'live');

        $this->generateSiteAndReload();

        $this->assertPageDataWithComponentHoldsThePublishableComponent('Conference', 'live');
        $this->assertComponentIsAnUnpublishedDraftOf('pending', 'live');
        $this->assertCommandOutputNotContains('could not be reproduced');
    }

    public function test_the_iri_of_a_site_page_inside_a_component_field_links_to_the_reloaded_page(): void
    {
        $this->siteHasAPageAndACollectionHoldingItsIri('blog', '/blog', 'parentPage');
        $this->generateSite();

        $this->assertGeneratedFixturesContain("->linkIri('defaultQueryParameters', key: 'parentPage', page: 'blog');");
        $this->assertGeneratedFixturesContain("'pagination' => false, 'parentPage' => null");
        $this->assertCommandOutputNotContains('could not be reproduced');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertCollectionHoldsTheIriOfThePage('blog', 'parentPage');
    }

    public function test_the_iri_of_something_a_scaffold_cannot_link_to_inside_a_field_is_reported(): void
    {
        $this->siteHasACollectionHoldingTheIriOfRoutelessPageData('draft');
        $this->generateSite();

        $this->assertCommandOutputContains('could not be reproduced');
        $this->assertCommandOutputContains('Collection::$defaultQueryParameters[');
        $this->assertCommandOutputContains('] holds the IRI');
        $this->assertCommandOutputContains('which is not a page, layout, routed page data or route of the generated site, so it is copied as it is.');
        $this->assertGeneratedFixturesNotContain('linkIri(');
    }

    public function test_a_page_with_no_route_that_is_reachable_without_one_keeps_its_flag(): void
    {
        $this->siteHasAPageReachableWithoutARoute('landing');
        $this->generateSiteAndReload();

        $this->assertPageIsReachableWithoutARoute('landing');
        $this->assertGeneratedFixturesContain('->reachableWithoutRoute()');
    }

    public function test_an_uploaded_file_comes_back_with_the_same_content(): void
    {
        $this->siteHasAnUploadableComponentWithTheFile('image.png');
        $this->generateSiteAndReload();

        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');
    }

    public function test_an_upload_stored_under_a_php_temp_name_is_exported_under_the_name_its_reload_is_stored_under(): void
    {
        $this->siteHasAnUploadableComponentWhoseFileIsStoredAs('components/phpGPCPOl', 'image.png');
        $this->generateSiteAndReload();

        $this->assertGeneratedFixturesContain('assets/phpgpcpol.png');
        $this->assertExportedAssets(['phpgpcpol.png']);
        $this->assertUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->generateSiteAgain();

        $this->assertGenerationIdenticalToThePreviousOne();
    }

    public function test_identical_uploads_under_different_stored_names_are_exported_as_one_asset_and_both_come_back(): void
    {
        $this->siteHasAnUploadableComponentWhoseFileIsStoredAs('components/logo-0a1b2c3d.png', 'image.png');
        $this->siteHasAnUploadableComponentWhoseFileIsStoredAs('components/phpGPCPOl', 'image.png');
        $this->generateSiteAndReload();

        $this->assertExportedAssets(['logo.png']);
        $this->assertEveryUploadableComponentHasAStoredFileWithTheSameContentAs('image.png');

        $this->generateSiteAgain();

        $this->assertGenerationIdenticalToThePreviousOne();
    }

    public function test_a_group_owned_by_a_component_keeps_its_components(): void
    {
        $this->siteHasAComponentWhoseOwnGroupHoldsAComponent('Tabs', 'panels', 'Panel');
        $this->generateSiteAndReload();

        $this->assertComponentOwnsAGroupHoldingTheComponent('Tabs', 'panels', 'Panel');
    }

    public function test_a_group_owned_by_a_component_on_a_nested_page_keeps_its_components(): void
    {
        $this->siteHasAComponentWithAnOwnGroupOnAChildPage('Tabs', 'panels', 'Panel', 'child', '/parent/child', 'parent');
        $this->generateSite();

        $this->assertGeneratedFixturesContain('->nested(');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertComponentOwnsAGroupHoldingTheComponent('Tabs', 'panels', 'Panel');
        $this->assertPageHasTheParentPage('child', 'parent');
    }

    public function test_a_group_shared_by_a_layout_and_a_page_through_a_location_reference_stays_one_group(): void
    {
        $this->siteHasASharedGroup('nav', 'main', 'home', 'site');
        $this->generateSiteAndReload();

        $this->assertComponentGroupsWithTheReference(1, 'nav_site');
        $this->assertComponentGroupBelongsToTheLayoutAndThePage('nav_site', 'main', 'home');
    }

    public function test_a_component_keeps_its_route_dates_inherited_and_non_public_properties(): void
    {
        $this->siteHasANavigationLink('/about', '2027-01-01T10:00:00+00:00', 'dark', 'check');
        $this->generateSiteAndReload();

        $this->assertNavigationLinkPointsAtTheRoute('/about');
        $this->assertNavigationLinkHas('2027-01-01T10:00:00+00:00', 'dark', 'check');
        $this->assertGeneratedFixturesContain("->linkRoute('route', routeName: 'about');");
    }

    public function test_page_and_route_fields_survive_including_scheduled_and_draft_routes_and_redirects(): void
    {
        $this->siteHasAPageWithAMetaDescriptionGoingLiveAt('about', 'About us', '/about', '2999-01-01T00:00:00+00:00');
        $this->siteHasAPageWithNoGoLiveDate('hidden', '/hidden');
        $this->siteHasARedirect('/old-about', '/about');
        $this->generateSiteAndReload();

        $this->assertPageHasTheMetaDescription('about', 'About us');
        $this->assertRouteGoesLiveAt('/about', '2999-01-01T00:00:00+00:00');
        $this->assertRouteHasNoGoLiveDate('/hidden');
        self::assertRouteRedirectsTo('/old-about', '/about');
    }

    public function test_a_page_data_property_holding_a_component_keeps_it(): void
    {
        $this->siteHasAPageDataWithComponent('Conference', 'Intro');
        $this->generateSiteAndReload();

        $this->assertPageDataWithComponentHoldsTheComponent('Conference', 'Intro');
    }

    public function test_page_data_with_no_route_comes_back_without_a_route(): void
    {
        $this->siteHasAPageDataTitledWithNoRoute('Draft');
        $this->generateSiteAndReload();

        $this->assertPageDataWithNoRoute(1);
    }

    public function test_two_page_data_with_the_same_title_both_come_back(): void
    {
        $this->siteHasAPageDataTitledAtTheRoute('Same', '/same-one');
        $this->siteHasAPageDataTitledAtTheRoute('Same', '/same-two');
        $this->generateSiteAndReload();

        $this->assertPageDataTitled(2, 'Same');
    }

    public function test_the_generated_class_is_named_after_the_output_file(): void
    {
        $this->siteHasGroupsThatAllowAndHoldADummyComponent();
        $this->generateSiteAndReload('SnapshotFixtures.php');

        $this->assertGroupOfThePageAllowsOnly('primary', 'home', 'DummyComponent', 1);
    }

    public function test_a_page_with_no_route_comes_back_without_a_route(): void
    {
        $this->siteHasARoutelessPageWithARoutedChild('parent', 'child', '/parent/child');
        $this->generateSiteAndReload();

        $this->assertPageHasNoRoute('parent');
        $this->assertRouteBelongsToThePage('/parent/child', 'child');
        self::assertSame(1, self::countEntities(Route::class));
    }

    public function test_a_pages_ui_component_comes_back_as_the_full_cwa_page_component_name(): void
    {
        $this->siteHasAPageWithTheUiComponent('legacy', 'PrimaryPageTemplate', '/legacy');
        $this->siteHasAPageWithTheUiComponent('seeded', 'CwaPagePrimaryPageTemplate', '/seeded');
        $this->generateSiteAndReload();

        $this->assertPageHasTheUiComponent('legacy', 'CwaPagePrimaryPageTemplate');
        $this->assertPageHasTheUiComponent('seeded', 'CwaPagePrimaryPageTemplate');
    }

    public function test_a_scaffold_written_in_the_documented_style_generates_itself(): void
    {
        $this->siteIsLoadedFromTheScaffold('scaffolds/SiteScaffold.php');
        $this->generateSite('SiteScaffold.php');

        $this->assertGeneratedFixturesIdenticalTo('scaffolds/SiteScaffold.php');
        $this->assertExportedAssetsIdenticalTo('scaffolds/assets');
        $this->assertCommandOutputNotContains('could not be reproduced');
    }

    public function test_long_html_with_and_without_newlines_reloads_byte_for_byte(): void
    {
        $this->siteHasAnHtmlComponentForEachLongHtmlSample();
        $this->generateSite();

        $this->assertNoGeneratedLineLongerThan(5100);
        $this->assertGeneratedFixturesContain("<<<'HTML'");

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertEveryHtmlComponentHoldsExactlyItsSample();
    }

    public function test_a_scheduled_published_at_comes_back_exact_and_a_past_one_is_published_at_load_time(): void
    {
        $this->siteHasAComponentPublishedAt('past', '2020-01-01T00:00:00+00:00');
        $this->siteHasAComponentPublishedAt('soon', '2099-01-15T09:00:00+00:00');
        $this->generateSite();

        $this->assertGeneratedFixturesContain('$cwa->publish($pastDummyPublishableComponent);');
        $this->assertGeneratedFixturesContain("\$cwa->publish(\$soonDummyPublishableComponent, at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'));");
        $this->assertGeneratedFixturesNotContain('2020-01-01');
        $this->assertGeneratedFixturesNotContain('setPublishedAt');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertComponentIsPublishedAt('soon', '2099-01-15T09:00:00+00:00');
        $this->assertComponentWasPublishedWhenItWasLoaded('past');
    }

    public function test_with_keep_timestamps_every_published_at_and_live_at_comes_back_exact(): void
    {
        $this->siteHasAComponentPublishedAt('past', '2020-01-01T00:00:00+00:00');
        $this->siteHasAPageWithAMetaDescriptionGoingLiveAt('about', 'About us', '/about', '2001-02-03T04:05:06+00:00');
        $this->generateSiteAndReload(options: ['--keep-timestamps' => true]);

        $this->assertComponentIsPublishedAt('past', '2020-01-01T00:00:00+00:00');
        $this->assertRouteGoesLiveAt('/about', '2001-02-03T04:05:06+00:00');
    }

    public function test_a_component_in_a_layout_group_and_in_a_nested_pages_group_is_one_row_in_two_positions(): void
    {
        $this->siteHasANavigationLinkInALayoutAndANestedPage('Contact us', 'header', 'primary', 'team', 'about');
        $this->generateSite();

        $this->assertGeneratedFixturesContain('private DummyNavigationLink $contactUsDummyNavigationLink;');
        $this->assertGeneratedFixturesContain('private function teamPage(CwaFixtureBuilder $cwa): void');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertNavigationLinksLabelledInPositions(1, 'Contact us', 2);
    }

    public function test_a_grandchild_page_comes_back_under_its_parent(): void
    {
        $this->siteHasAGrandchildPage('grandchild', '/parent/child/grandchild', 'child', '/parent/child', 'parent', '/parent');
        $this->generateSite();

        $this->assertGeneratedFixturesContain('->nested($this->childPage(...));');
        $this->assertGeneratedFixturesContain('->nested($this->grandchildPage(...));');
        $this->assertGeneratedFixturesContain('private function grandchildPage(CwaFixtureBuilder $cwa): void');

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertPageHasTheParentPage('grandchild', 'child');
        $this->assertPageHasTheParentPage('child', 'parent');
        $this->assertRouteBelongsToThePage('/parent/child/grandchild', 'grandchild');
    }

    public function test_a_navigation_link_to_a_redirect_route_and_to_a_page_with_a_derived_route_name_keeps_its_route(): void
    {
        $this->siteHasAPageWithADerivedRouteName('about', '/about');
        $this->siteHasARedirect('/old-about', '/about');
        $this->siteHasANavigationLinkToTheRoute('Old about', '/old-about');
        $this->siteHasANavigationLinkToTheRoute('About', '/about');
        $this->generateSite();

        $this->assertGeneratedFixturesContain("->linkRoute('route', routeName: 'old-about');");
        $this->assertGeneratedFixturesContain("->linkRoute('route', routeName: 'about');");
        $this->assertGeneratedFixturesNotContain("route: '/about', routeName:");
        $this->assertGeneratedFixturesNotContain("name: 'old-about'");

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertNavigationLinkLabelledPointsAtTheRoute('Old about', '/old-about');
        $this->assertNavigationLinkLabelledPointsAtTheRoute('About', '/about');
    }

    public function test_a_component_related_to_another_component_keeps_the_relation(): void
    {
        $this->siteHasARelatedComponentInTheSameGroup('Card', 'Teaser', 'primary', 'home');
        $this->siteHasARelatedComponentAcrossMethods('Banner', 'main', 'Promo', 'home');
        $this->generateSiteAndReload();

        $this->assertComponentIsRelatedToTheComponent('Card', 'Teaser');
        $this->assertComponentIsRelatedToTheComponent('Banner', 'Promo');
        $this->assertComponentsLabelled(1, 'Teaser');
        $this->assertComponentsLabelled(1, 'Promo');
    }

    public function test_page_data_keeps_a_route_association(): void
    {
        $this->siteHasAPageWithADerivedRouteName('about', '/about');
        $this->siteHasAPageDataWithRoute('Event', '/event', '/about');
        $this->generateSite();

        $this->assertGeneratedFixturesContain("->linkRoute('relatedRoute', routeName: 'about')");

        $this->purgeAndLoadGeneratedFixtures();

        $this->assertPageDataWithRouteHasTheRelatedRoute('Event', '/about');
    }

    public function test_a_draft_owned_group_is_reported_as_not_reproduced(): void
    {
        $this->siteHasAPublishedComponentWithADraftOwningAGroup('live', 'live-draft', 'panels');
        $this->generateSite();

        $this->assertCommandOutputContains('could not be reproduced');
        $this->assertCommandOutputContains('panels_');
        $this->assertGeneratedFixturesNotContain("->group('panels')");
    }

    public function test_a_page_and_page_data_whose_own_routes_redirect_keep_their_redirects_and_regenerate_identically(): void
    {
        $this->siteHasAPageWithADerivedRouteName('home', '/');
        $this->siteHasAPageWithADerivedRouteName('graphs', '/graphs');
        $this->siteHasAPageWithADerivedRouteName('findings', '/graphs/findings');
        $this->siteHasAPageDataTitledAtTheRoute('Event', '/event');
        $this->sitesRouteRedirectsTo('/', '/graphs');
        $this->sitesRouteRedirectsTo('/graphs', '/graphs/findings');
        $this->sitesRouteRedirectsTo('/event', '/graphs');
        $this->generateSiteAndReload();

        $this->assertGeneratedFixturesContain("->redirectTo(routeName: 'graphs');");
        $this->assertGeneratedFixturesContain("->redirectTo(routeName: 'graphs-findings');");
        $this->assertGeneratedFixturesNotContain('$cwa->redirect(');
        $this->assertCommandOutputNotContains('could not be reproduced');
        $this->assertRouteBelongsToThePage('/', 'home');
        $this->assertRouteBelongsToThePage('/graphs', 'graphs');
        self::assertRouteRedirectsTo('/', '/graphs');
        self::assertRouteRedirectsTo('/graphs', '/graphs/findings');
        self::assertRouteRedirectsTo('/event', '/graphs');
        self::assertRouteDoesNotRedirect('/graphs/findings');

        $this->generateSiteAgain();

        $this->assertGenerationIdenticalToThePreviousOne();
    }
}
