<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Orphan;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class OrphanedResourcesTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use JsonTrait;
    use OrphanedResourcesTrait;
    use PublishableTrait;
    use SiteTrait;

    public function test_a_scan_reports_an_orphaned_group_an_empty_position_and_an_unused_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createRoutedPageWithComponent('/clean');
        $orphanedGroup = self::createOrphanedComponentGroup();
        $emptyPosition = self::createEmptyPositionOnPage($site['page']);
        self::persistAndFlush($component = new DummyComponent());
        $now = new \DateTimeImmutable();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNow('generatedAt', $now);
        self::assertJsonNodeListsExactly('componentGroups', [$orphanedGroup]);
        self::assertJsonNodeListsExactly('componentPositions', [$emptyPosition]);
        self::assertJsonNodeListsExactly('components', [self::iriOf($component)]);
    }

    public function test_a_scan_follows_an_orphaned_group_down_to_everything_nothing_else_links_into_the_tree(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createOrphanedGroupWithNestedSharedPageDataAndDraftDescendants();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('componentGroups', self::irisNamed($iris, ['chain_group', 'chain_owned_group']));
        self::assertJsonNodeListsExactly('componentPositions', self::irisNamed($iris, ['chain_position', 'chain_owned_position', 'cycle_position', 'shared_position', 'page_data_position', 'parent_typed_position', 'published_position']));
        self::assertJsonNodeListsExactly('components', self::irisNamed($iris, ['chain_component', 'chain_owned_component', 'chained_published']));
    }

    public function test_a_draft_and_a_component_used_only_through_a_page_data_property_are_not_reported(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createRoutedPageWithComponent('/clean');
        [$published] = self::createPublishedWithDraft();
        self::placeOnPageForOrphans($site['page'], self::iriOf($published));
        self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentGroups', 0);
        self::assertJsonNodeCount('componentPositions', 0);
        self::assertJsonNodeCount('components', 0);
    }

    public function test_a_published_component_that_nothing_uses_is_reported_but_not_its_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published] = self::createPublishedWithDraft();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertJsonNodeListsExactly('components', [self::iriOf($published)]);
    }

    public function test_the_report_is_never_stored_by_a_shared_cache_because_a_later_scan_is_not_a_write_that_could_purge_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        $cacheControl = (string) $client->getResponse()?->getHeaders(false)['cache-control'][0];
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringNotContainsString('s-maxage', $cacheControl);
    }

    public function test_a_clean_site_gives_an_empty_report(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRoutedPageWithComponent('/clean');

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentGroups', 0);
        self::assertJsonNodeCount('componentPositions', 0);
        self::assertJsonNodeCount('components', 0);
    }

    public function test_a_group_owned_only_by_a_component_placed_on_a_page_is_not_reported_nor_anything_in_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createRoutedPageWhoseGroupHoldsAComponentOwningAGroup();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::assertResponseStatusCodeSame(202);
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentGroups', 0);
        self::assertJsonNodeCount('componentPositions', 0);
        self::assertJsonNodeCount('components', 0);
    }

    public function test_a_later_scan_replaces_the_stored_report(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::persistAndFlush($component = new DummyComponent());
        $componentIri = self::iriOf($component);

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::removeFromDatabase($componentIri);
        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertJsonNodeCount('components', 0);
    }

    public function test_fetching_the_report_before_any_scan_has_run_is_a_404(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_the_scan_cannot_be_requested_with_get(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources/scan');

        self::assertResponseStatusCodeSame(405);
    }

    public function test_a_user_without_the_administrator_role_cannot_request_a_scan(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');

        self::assertResponseStatusCodeSame(403);
        self::assertNull(self::storedOrphanedResourcesReport(), 'An orphaned resources report was stored');
    }

    public function test_a_user_without_the_administrator_role_cannot_fetch_the_report(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::storeOrphanedResourcesReport();

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_an_anonymous_request_for_a_scan_is_refused_by_the_test_applications_firewall_before_the_operation_is_reached(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/scan');

        self::assertResponseStatusCodeSame(401);
        self::assertNull(self::storedOrphanedResourcesReport(), 'An orphaned resources report was stored');
    }

    public function test_an_anonymous_request_for_the_report_is_refused_by_the_operations_own_security(): void
    {
        $client = static::createClient();
        self::storeOrphanedResourcesReport();

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(401);
    }
}
