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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class OrphanedResourcesDeleteTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;
    use JsonTrait;
    use OrphanedResourcesTrait;

    public function test_deleting_selected_orphans_deletes_only_those_each_with_its_cascade(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestOrphanDeletion($client, self::irisNamed($iris, ['orphaned_group', 'unused_published', 'owning_component']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('deleted.componentGroups', self::irisNamed($iris, ['orphaned_group', 'owned_group']));
        self::assertJsonNodeListsExactly('deleted.componentPositions', self::irisNamed($iris, ['orphaned_group_position', 'orphaned_group_empty_position', 'owned_position']));
        self::assertJsonNodeListsExactly('deleted.components', self::irisNamed($iris, ['orphaned_group_component', 'unused_published', 'unused_published_draft', 'owning_component', 'owned_component']));
        self::assertJsonNodeCount('rejected', 0);
        self::assertResourceNotExists($iris['orphaned_group']);
        self::assertComponentExistsInDatabase($iris['unused_published_draft'], false);
        self::assertResourceNotExists($iris['owned_component']);
        self::assertResourceExists($iris['unused_component']);
        self::assertResourceExists($iris['empty_position']);
    }

    public function test_deleting_all_orphans_deletes_everything_reported_with_its_cascade_and_nothing_in_use(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('deleted.componentGroups', self::irisNamed($iris, ['orphaned_group', 'owned_group']));
        self::assertJsonNodeListsExactly('deleted.componentPositions', self::irisNamed($iris, ['empty_position', 'orphaned_group_position', 'orphaned_group_empty_position', 'owned_position']));
        self::assertJsonNodeListsExactly('deleted.components', self::irisNamed($iris, ['unused_component', 'orphaned_group_component', 'unused_published', 'unused_published_draft', 'owning_component', 'owned_component']));
        self::assertJsonNodeCount('rejected', 0);
        foreach (['page_group', 'placed_position', 'placed_component', 'placed_published', 'page_data_component', 'parent_typed_component'] as $name) {
            self::assertResourceExists($iris[$name]);
        }
        self::assertComponentExistsInDatabase($iris['placed_published_draft']);
    }

    public function test_a_deletion_purges_the_deleted_resources_from_the_cache(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestOrphanDeletion($client, [$iris['orphaned_group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($iris['orphaned_group']);
        self::assertCacheTagPurged($iris['orphaned_group_component']);
    }

    public function test_a_resource_in_use_is_rejected_and_kept(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestOrphanDeletion($client, self::irisNamed($iris, ['placed_component', 'placed_published_draft', 'unused_component']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('deleted.components', [$iris['unused_component']]);
        self::assertJsonNodeCount('rejected', 2);
        self::assertJsonNodeSame('rejected[0].iri', $iris['placed_component']);
        self::assertJsonNodeSame('rejected[0].reason', 'not_orphaned');
        self::assertJsonNodeSame('rejected[1].iri', $iris['placed_published_draft']);
        self::assertJsonNodeSame('rejected[1].reason', 'not_orphaned');
        self::assertResourceExists($iris['placed_component']);
        self::assertComponentExistsInDatabase($iris['placed_published_draft']);
    }

    public function test_an_iri_that_no_longer_exists_or_is_not_a_resource_is_rejected_cleanly(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();
        self::removeFromDatabase($iris['unused_component']);

        self::requestOrphanDeletion($client, [$iris['unused_component'], '/not/a/resource']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('deleted.components', 0);
        self::assertJsonNodeSame('rejected[0].iri', $iris['unused_component']);
        self::assertJsonNodeSame('rejected[0].reason', 'not_found');
        self::assertJsonNodeSame('rejected[1].iri', '/not/a/resource');
        self::assertJsonNodeSame('rejected[1].reason', 'not_found');
    }

    public function test_the_stored_report_no_longer_lists_what_was_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestOrphanDeletion($client, self::irisNamed($iris, ['unused_component', 'orphaned_group']));
        self::requestJsonLd($client, 'GET', '/_/orphaned_resources');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeListsExactly('componentGroups', [$iris['owned_group']]);
        self::assertJsonNodeListsExactly('componentPositions', self::irisNamed($iris, ['empty_position', 'owned_position']));
        self::assertJsonNodeListsExactly('components', self::irisNamed($iris, ['unused_published', 'owning_component', 'owned_component']));
    }

    #[DataProvider('invalidDeletionBodies')]
    public function test_a_request_must_name_its_iris_or_ask_for_all_but_not_both(string $body): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertResourceExists($iris['unused_component']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidDeletionBodies(): iterable
    {
        yield 'an empty body' => ['{}'];
        yield 'all set to false' => ['{"all": false}'];
        yield 'all with iris' => ['{"all": true, "iris": []}'];
    }

    public function test_the_delete_operation_cannot_be_requested_with_get(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'GET', '/_/orphaned_resources/delete');

        self::assertResponseStatusCodeSame(405);
    }

    public function test_a_user_without_the_administrator_role_cannot_delete_orphans(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(403);
        self::assertResourceExists($iris['unused_component']);
    }

    public function test_an_anonymous_request_is_refused_by_the_test_applications_firewall_before_the_operation_is_reached(): void
    {
        $client = static::createClient();
        $iris = self::createSiteWithEveryKindOfOrphanAndEveryKindOfUse();

        self::requestJsonLd($client, 'POST', '/_/orphaned_resources/delete', '{"all": true}');

        self::assertResponseStatusCodeSame(401);
        self::assertResourceExists($iris['unused_component']);
    }
}
