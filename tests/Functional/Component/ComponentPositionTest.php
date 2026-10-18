<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Component;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithRestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentPositionTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    public function test_create_a_new_component_position_resource(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        $message = self::componentGroupMercureMessage();
        self::assertArrayHasKey('createdAt', $message);
        self::assertArrayHasKey('modifiedAt', $message);
        self::assertCount(1, $message['componentPositions']);
    }

    public function test_sort_collection_is_not_included_in_a_component_position_response(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(1);

        self::requestJsonLd($client, 'GET', $group['positions'][0]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('sortCollection');
    }

    #[DataProvider('restrictedComponentPlacements')]
    public function test_i_can_restrict_which_components_are_permitted_to_be_inside_a_component_collection_and_a_component_that_must_be_specifically_defined_as_being_allowed_to_pass_validation(string $component, int $status, string $allowedComponent): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($group['group'], $allowedComponent);
        $components = ['dummy' => self::createDummyComponent()];
        $restricted = new RestrictedComponent();
        self::persistAndFlush($restricted);
        $components['restricted'] = self::iriOf($restricted);

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $components[$component]]);

        self::assertResponseStatusCodeSame($status);
    }

    public static function restrictedComponentPlacements(): iterable
    {
        yield 'a dummy component in a group allowing only restricted components' => ['dummy', 422, '/component/restricted_components'];
        yield 'a restricted component in a group allowing it' => ['restricted', 201, '/component/restricted_components'];
        yield 'a restricted component in a group with no allowed components' => ['restricted', 422, ''];
        yield 'a dummy component in a group with no allowed components' => ['dummy', 201, ''];
    }

    public function test_a_component_created_straight_into_a_component_group_must_be_one_the_group_allows(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($group['group'], '/component/restricted_components');

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0]');
        self::assertSame(0, self::countEntities(ComponentPosition::class));
    }

    public function test_component_position_sort_value_auto_increments(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(1);

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $group['components'][0]]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('sortValue', 1);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
    }

    public function test_a_position_inserted_after_another_moves_along_the_positions_it_runs_into(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(3);
        [$p0, $p1, $p2] = $group['positions'];

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $group['components'][0], 'after' => $p0]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('sortValue', 1);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        self::assertSortValues([$p0 => 0, $p1 => 2, $p2 => 3]);
    }

    public function test_a_position_moved_after_a_later_one_keeps_every_value_that_still_fits(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(4);
        [$p0, $p1, $p2, $p3] = $group['positions'];

        self::requestJsonLd($client, 'PATCH', $p2, ['componentGroup' => $group['group'], 'component' => $group['components'][0], 'after' => $p3]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 4);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        self::assertSortValues([$p0 => 0, $p1 => 1, $p3 => 3, $p2 => 4]);
    }

    public function test_a_position_moved_before_an_earlier_one_keeps_every_value_that_still_fits(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(4);
        [$p0, $p1, $p2, $p3] = $group['positions'];

        self::requestJsonLd($client, 'PATCH', $p2, ['componentGroup' => $group['group'], 'component' => $group['components'][0], 'before' => $p1]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 2);
        self::assertSortValues([$p0 => 0, $p2 => 2, $p1 => 3, $p3 => 4]);
    }

    public function test_inserting_a_component_position_with_no_anchor_does_not_shift_existing_positions(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(3);
        [$p0, $p1, $p2] = $group['positions'];

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $group['components'][0]]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('sortValue', 3);
        self::assertSortValues([$p0 => 0, $p1 => 1, $p2 => 2]);
    }

    public function test_moving_a_component_position_changes_only_the_positions_whose_values_no_longer_fit(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(5);
        [$p0, $p1, $p2, $p3, $p4] = $group['positions'];

        self::requestJsonLd($client, 'PATCH', $p1, ['after' => $p3]);

        self::assertResponseStatusCodeSame(200);
        self::assertSortValues([$p0 => 0, $p2 => 2, $p3 => 3, $p1 => 4, $p4 => 5]);
        self::assertPositionUpdatesPublishedForExactly([$p1, $p4]);
    }

    public function test_moving_a_component_position_into_another_group_after_an_anchor_resolves_to_unique_sort_values(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(3);
        $other = self::createGroupWithComponentIris(2, 'other_collection');
        [$p0, $p1, $p2] = $group['positions'];

        self::requestJsonLd($client, 'PATCH', $other['positions'][0], ['componentGroup' => $group['group'], 'after' => $p0]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 1);
        self::assertSortValues([$p0 => 0, $other['positions'][0] => 1, $p1 => 2, $p2 => 3]);
    }

    public function test_moving_a_component_position_within_a_group_that_already_holds_a_duplicate_sort_value_resolves_to_unique_sort_values(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C1 D3');

        self::requestJsonLd($client, 'PATCH', $names['A'], ['after' => $names['D']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'B1 C2 D3 A4', $names);
    }

    public function test_cannot_create_a_dynamic_position_with_page_data_property_but_no_page_data_class(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'component']);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_cannot_create_a_dynamic_position_with_page_data_class_but_no_page_data_property(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataClass' => PageDataWithComponent::class]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_cannot_create_a_dynamic_position_with_a_page_data_class_that_is_not_a_known_page_data_resource(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'component', 'pageDataClass' => 'App\NotAPageDataClass']);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_cannot_create_a_dynamic_position_where_page_data_property_is_not_a_component_typed_property_on_the_page_data_class(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'notAProperty', 'pageDataClass' => PageDataWithComponent::class]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_cannot_create_a_dynamic_position_where_the_resolved_component_type_is_not_in_allowed_components(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($group['group'], '/component/dummy_components');

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'publishableComponent', 'pageDataClass' => PageDataWithComponent::class]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_can_create_a_dynamic_position_with_a_valid_page_data_class_and_page_data_property(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'component', 'pageDataClass' => PageDataWithComponent::class]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_can_create_a_dynamic_position_where_the_component_type_matches_allowed_components(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($group['group'], '/component/dummy_components');

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'component', 'pageDataClass' => PageDataWithComponent::class]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_cannot_create_a_dynamic_position_resolving_to_an_explicit_allow_only_component_type_in_a_group_without_allowed_components(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'restrictedComponent', 'pageDataClass' => PageDataWithRestrictedComponent::class]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_can_create_a_dynamic_position_resolving_to_an_explicit_allow_only_component_type_when_the_group_explicitly_allows_it(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($group['group'], '/component/restricted_components');

        self::postPosition($client, ['componentGroup' => $group['group'], 'pageDataProperty' => 'restrictedComponent', 'pageDataClass' => PageDataWithRestrictedComponent::class]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_an_admin_can_read_page_data_class_from_a_dynamic_position(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataWithRoute('/page-data');

        self::requestJsonLd($client, 'GET', self::iriOf($site['position']));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('pageDataClass', PageDataWithComponent::class);
    }

    private static function loggedInClient(): Client
    {
        $client = static::createClient();
        self::loginAsUser($client);

        return $client;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function postPosition(Client $client, array $body): void
    {
        self::requestJsonLd($client, 'POST', '/_/component_positions', $body);
    }
}
