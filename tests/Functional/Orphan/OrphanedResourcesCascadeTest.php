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
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;

final class OrphanedResourcesCascadeTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use JsonTrait;
    use OrphanedResourcesTrait;
    use PublishableTrait;

    public function test_deleting_a_group_removes_the_groups_owned_by_the_components_it_cascades_to(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createRoutedPageWhoseGroupHoldsAComponentOwningAGroup();

        self::requestJsonLd($client, 'DELETE', $iris['outer_group']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($iris['outer_component']);
        self::assertResourceNotExists($iris['inner_group']);
        self::assertResourceNotExists($iris['inner_component']);
        self::scanAndFetchOrphanReport($client);
        self::assertEmptyOrphanReport();
    }

    public function test_deleting_an_orphaned_published_component_removes_its_draft(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        [$published, $draft] = self::createPublishedWithDraft();
        $publishedIri = self::iriOf($published);
        $draftIri = self::iriOf($draft);

        self::requestOrphanDeletion($client, [$publishedIri]);

        self::assertResponseStatusCodeSame(200);
        self::assertComponentExistsInDatabase($publishedIri, false);
        self::assertComponentExistsInDatabase($draftIri, false);
        self::scanAndFetchOrphanReport($client);
        self::assertJsonNodeCount('components', 0);
    }

    public function test_a_published_component_removed_by_a_group_cascade_takes_its_draft_with_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $groupIri = self::createOrphanedComponentGroup();
        [$published, $draft] = self::createPublishedWithDraft();
        $publishedIri = self::iriOf($published);
        $draftIri = self::iriOf($draft);
        self::placeInGroupForOrphans($groupIri, $publishedIri);

        self::requestJsonLd($client, 'DELETE', $groupIri);

        self::assertResponseStatusCodeSame(204);
        self::assertComponentExistsInDatabase($publishedIri, false);
        self::assertComponentExistsInDatabase($draftIri, false);
        self::scanAndFetchOrphanReport($client);
        self::assertEmptyOrphanReport();
    }

    public function test_deleting_every_orphan_in_bulk_deletes_exactly_what_the_scan_listed_through_orphaned_parents_and_the_next_scan_finds_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createOrphanedGroupWithNestedSharedPageDataAndDraftDescendants();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('deleted.componentGroups', self::irisNamed($iris, ['chain_group', 'chain_owned_group']));
        self::assertJsonNodeListsExactly('deleted.componentPositions', self::irisNamed($iris, ['chain_position', 'chain_owned_position', 'cycle_position', 'shared_position', 'page_data_position', 'parent_typed_position', 'published_position']));
        self::assertJsonNodeListsExactly('deleted.components', self::irisNamed($iris, ['chain_component', 'chain_owned_component', 'chained_published', 'chained_published_draft']));
        self::assertJsonNodeCount('rejected', 0);
        self::assertResourceNotExists($iris['chain_owned_group']);
        self::assertResourceNotExists($iris['chain_owned_component']);
        self::assertComponentExistsInDatabase($iris['chained_published_draft'], false);
        foreach (['shared_component', 'shared_live_position', 'shared_owned_group', 'shared_owned_position', 'shared_owned_component', 'page_data_component', 'parent_typed_component'] as $name) {
            self::assertResourceExists($iris[$name]);
        }
        self::scanAndFetchOrphanReport($client);
        self::assertEmptyOrphanReport();
    }

    public function test_a_resource_orphaned_only_through_its_parent_can_be_selected_for_deletion_on_its_own(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createOrphanedGroupWithNestedSharedPageDataAndDraftDescendants();

        self::requestOrphanDeletion($client, self::irisNamed($iris, ['chain_owned_position', 'chain_owned_component']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('rejected', 0);
        self::assertJsonNodeListsExactly('deleted.componentPositions', [$iris['chain_owned_position']]);
        self::assertJsonNodeListsExactly('deleted.components', [$iris['chain_owned_component']]);
        self::assertResourceExists($iris['chain_owned_group']);
    }

    public function test_deleting_every_orphan_in_bulk_leaves_nothing_for_the_next_scan(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::scanAndFetchOrphanReport($client);
        self::assertEmptyOrphanReport();
        self::assertComponentExistsInDatabase($iris['placed_published_draft']);
        self::assertResourceExists($iris['page_data_component']);
        self::assertResourceExists($iris['parent_typed_component']);
    }

    private static function assertEmptyOrphanReport(): void
    {
        self::assertJsonNodeCount('componentGroups', 0);
        self::assertJsonNodeCount('componentPositions', 0);
        self::assertJsonNodeCount('components', 0);
    }
}
