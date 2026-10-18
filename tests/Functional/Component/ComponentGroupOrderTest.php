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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentGroupOrderTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    private const string FULL = 'The component group `collection` is full: it holds at most 1 component.';

    public function test_an_insert_after_a_position_with_room_after_it_changes_no_other_position(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10 C20');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('sortValue', 1);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 B10 C20', $names);
    }

    public function test_an_insert_after_a_position_with_no_room_after_it_moves_the_positions_after_it_along_by_one(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C2');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 B2 C3', $names);
    }

    public function test_an_insert_moves_only_the_positions_it_runs_into(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C2 D10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 B2 C3 D10', $names);
    }

    public function test_an_insert_before_the_first_position_starts_the_group_at_0(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'before' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'N0 A1 B2', $names);
    }

    public function test_an_insert_with_no_anchor_goes_at_the_end(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 B10 N11', $names);
    }

    public function test_an_insert_after_the_first_of_two_positions_sharing_a_value_removes_the_tie(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A5 B5');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A5 N6 B7', $names);
    }

    public function test_an_insert_before_the_second_of_two_positions_sharing_a_value_removes_the_tie(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A5 B5');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'before' => $names['B']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A5 N6 B7', $names);
    }

    public function test_an_insert_from_a_client_that_has_not_seen_another_editors_move_still_goes_after_its_anchor(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        $names = self::createGroupWithPositions('A0 B10 C20');

        self::sendOrder($client, $names['group'], [$names['A'], $names['C'], $names['B']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'A0 C20 B21', $names);

        $component = self::createDummyComponent();
        self::reissueToken($client, $user);
        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 C20 B21', $names);
    }

    #[DataProvider('anchorsNotInTheGroup')]
    public function test_an_anchor_that_is_not_in_the_group_is_a_422(string $anchor, string $position): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $other = self::createGroupWithPositions('X0', 'other_collection');
        self::deletePosition($names['B']);
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, $anchor => ($names + $other)[$position]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $anchor);
        self::assertStringContainsString('is not in the component group `collection`', self::jsonNode('violations[0].message'));
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public static function anchorsNotInTheGroup(): iterable
    {
        yield 'after a position of another group' => ['after', 'X'];
        yield 'before a position of another group' => ['before', 'X'];
        yield 'after a deleted position' => ['after', 'B'];
        yield 'before a deleted position' => ['before', 'B'];
    }

    #[DataProvider('anchorsThatAreNotPositions')]
    public function test_an_anchor_that_does_not_resolve_to_a_position_is_a_422_never_ignored(string $anchor, ?string $iri): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, $anchor => $iri ?? $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $anchor);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public static function anchorsThatAreNotPositions(): iterable
    {
        yield 'after a position that does not exist' => ['after', '/_/component_positions/00000000-0000-4000-8000-000000000000'];
        yield 'before a position that does not exist' => ['before', '/_/component_positions/00000000-0000-4000-8000-000000000000'];
        yield 'after an IRI that is not a resource' => ['after', '/nope'];
        yield 'before a component' => ['before', null];
    }

    #[DataProvider('anchors')]
    public function test_an_anchor_sent_with_a_sort_value_is_a_422(string $anchor): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, $anchor => $names['A'], 'sortValue' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $anchor);
        self::assertJsonNodeSame('violations[0].message', 'Send an anchor or a sortValue, not both: the server chooses the sortValue.');
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public static function anchors(): iterable
    {
        yield 'after' => ['after'];
        yield 'before' => ['before'];
    }

    public function test_an_insert_with_both_anchors_is_a_422_on_before(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A'], 'before' => $names['B']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'before');
        self::assertJsonNodeSame('violations[0].message', 'Send either after or before, not both.');
    }

    public function test_a_sort_value_sent_without_an_anchor_is_ignored(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'sortValue' => 0]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('sortValue', 11);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], 'A0 B10 N11', $names);
    }

    public function test_a_sort_value_in_a_patch_is_ignored(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'PATCH', $names['A'], '{"sortValue": 20}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 0);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public function test_an_anchored_insert_into_a_full_group_is_a_422(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0');
        self::setMaxComponents($names['group'], 1);
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentGroup');
        self::assertJsonNodeSame('violations[0].message', self::FULL);
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public function test_a_component_created_straight_into_a_group_is_placed_after_its_anchor(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $names['group'], 'after' => $names['A']]]]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('componentPositions[0]');
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 B2', $names);
    }

    public function test_a_component_created_straight_into_a_group_with_an_anchor_from_another_group_is_a_422_on_the_embedded_anchor(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $names['group'], 'before' => $other['X']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0].before');
        self::assertSame(3, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_created_straight_into_a_group_with_an_anchor_and_a_sort_value_is_a_422_on_the_embedded_anchor(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $names['group'], 'after' => $names['A'], 'sortValue' => 1]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0].after');
        self::assertSame(2, self::countEntities(ComponentPosition::class));
    }

    public function test_after_an_insert_the_group_lists_its_positions_by_sort_value_including_the_new_one(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');
        $component = self::createDummyComponent();

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component, 'after' => $names['A']]);
        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');

        self::requestJsonLd($client, 'GET', $names['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPositions', [$names['A'], $names['N'], $names['B']]);
    }

    public function test_positions_sharing_a_sort_value_are_listed_by_id(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A5 B5 C5');

        self::requestJsonLd($client, 'GET', $names['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPositions', [$names['A'], $names['B'], $names['C']]);
    }

    public function test_a_reorder_changes_the_fewest_positions(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10 C20');

        self::sendOrder($client, $names['group'], [$names['B'], $names['A'], $names['C']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'B10 A11 C20', $names);
        self::assertPositionUpdatesPublishedForExactly([$names['A']]);
    }

    public function test_a_reorder_never_gives_a_value_below_0(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C2');

        self::sendOrder($client, $names['group'], [$names['C'], $names['A'], $names['B']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'C2 A3 B4', $names);
        self::assertPositionUpdatesPublishedForExactly([$names['A'], $names['B']]);
    }

    public function test_a_reorder_removes_ties(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A5 B5 C5');

        self::sendOrder($client, $names['group'], [$names['A'], $names['B'], $names['C']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'A5 B6 C7', $names);
    }

    public function test_a_position_hidden_by_allowed_components_follows_its_current_predecessor(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 H10 B20');
        self::holdRestrictedComponent($names['H']);
        self::setAllowedComponent($names['group'], '/component/dummy_components');

        self::sendOrder($client, $names['group'], [$names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([[$names['B'], 20], [$names['A'], 21]]);
        self::assertGroupHoldsPositions($names['group'], 'B20 A21 H22', $names);
    }

    public function test_a_position_added_since_the_client_loaded_the_group_follows_its_current_predecessor(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10 N15');

        self::sendOrder($client, $names['group'], [$names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([[$names['B'], 10], [$names['N'], 15], [$names['A'], 16]]);
        self::assertGroupHoldsPositions($names['group'], 'B10 N15 A16', $names);
    }

    public function test_a_position_another_request_added_between_the_clients_read_and_its_reorder_is_in_the_reorder_response(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'GET', $names['group'] . '/order');
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 10]]);

        $component = self::createDummyComponent();
        self::reissueToken($client, $user);
        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $component]);
        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');

        self::reissueToken($client, $user);
        self::sendOrder($client, $names['group'], [$names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([[$names['B'], 10], [$names['N'], 11], [$names['A'], 12]]);
        self::assertJsonNodeSame('componentPositions', [$names['B'], $names['N'], $names['A']]);
        self::assertGroupHoldsPositions($names['group'], 'B10 N11 A12', $names);
    }

    public function test_a_position_deleted_since_the_client_loaded_the_group_is_ignored(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10 C20');
        self::deletePosition($names['C']);

        self::sendOrder($client, $names['group'], [$names['C'], $names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([[$names['B'], 10], [$names['A'], 11]]);
        self::assertGroupHoldsPositions($names['group'], 'B10 A11', $names);
    }

    /**
     * @param list<string> $order
     */
    #[DataProvider('invalidOrders')]
    public function test_an_order_naming_a_position_outside_the_group_or_one_position_twice_is_a_422(array $order, string $path): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $other = self::createGroupWithPositions('X0', 'other_collection');
        $iris = array_map(static fn (string $name): string => ($names + $other)[$name] ?? $name, $order);

        self::sendOrder($client, $names['group'], $iris);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $path);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public static function invalidOrders(): iterable
    {
        yield 'a position of another group' => [['B', 'X', 'A'], 'componentPositions[1]'];
        yield 'one position twice' => [['B', 'A', 'B'], 'componentPositions[2]'];
        yield 'an IRI that is not a position' => [['B', 'A', '/nope'], 'componentPositions[2]'];
    }

    public function test_the_reorder_response_is_what_a_fresh_read_of_the_order_returns(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10 C20');

        self::sendOrder($client, $names['group'], [$names['C'], $names['A'], $names['B']]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($names['group']);
        self::assertOrderSortValues([[$names['C'], 20], [$names['A'], 21], [$names['B'], 22]]);
        self::assertJsonNodeSame('componentPositions', [$names['C'], $names['A'], $names['B']]);
        self::assertPositionUpdatesPublishedForExactly([$names['A'], $names['B']]);
        $written = self::lastJson();
        self::requestJsonLd($client, 'GET', $names['group'] . '/order');
        $read = self::lastJson();
        unset($written['_metadata'], $read['_metadata']);
        self::assertSame($written, $read);
        self::assertJsonNodeSame('componentPositions', [$names['C'], $names['A'], $names['B']]);
    }

    public function test_anyone_who_can_read_the_group_can_read_its_order(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'GET', $names['group'] . '/order');

        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($names['group']);
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 10]]);
        self::assertJsonNodeSame('componentPositions', [$names['A'], $names['B']]);
    }

    #[DataProvider('orderPostfixes')]
    public function test_an_admin_reads_the_same_order_with_or_without_the_draft_query(string $postfix): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'GET', $names['group'] . $postfix);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($names['group']);
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 10]]);
    }

    public static function orderPostfixes(): iterable
    {
        yield 'without the draft query' => ['/order'];
        yield 'with the draft query' => ['/order?draft=1'];
    }

    public function test_the_order_of_a_group_is_addressed_by_its_reference_too(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'GET', '/_/component_groups/collection/order');

        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($names['group']);
    }

    public function test_the_order_of_a_group_lists_only_the_positions_the_group_shows(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A0 H10 B20');
        self::holdRestrictedComponent($names['H']);
        self::setAllowedComponent($names['group'], '/component/dummy_components');

        self::requestJsonLd($client, 'GET', $names['group'] . '/order');

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 20]]);
        self::assertJsonNodeSame('componentPositions', [$names['A'], $names['B']]);
    }

    public function test_the_order_of_a_group_with_no_positions_is_empty(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('');

        self::requestJsonLd($client, 'GET', $names['group'] . '/order');

        self::assertResponseStatusCodeSame(200);
        self::assertOrderSortValues([]);
    }

    public function test_the_order_of_a_group_that_does_not_exist_is_a_404(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/component_groups/not-a-group/order');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_reorder_ignores_the_maximum_number_of_components(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C2');
        self::setMaxComponents($names['group'], 1);

        self::sendOrder($client, $names['group'], [$names['C'], $names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'C2 B3 A4', $names);
    }

    public function test_a_position_moved_into_another_group_after_an_anchor_is_placed_after_it(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0 Y1', 'other_collection');

        self::requestJsonLd($client, 'PATCH', $other['Y'], ['componentGroup' => $names['group'], 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 1);
        self::assertGroupHoldsPositions($names['group'], 'A0 Y1 B2', ['Y' => $other['Y']] + $names);
        self::assertGroupHoldsPositions($other['group'], 'X0', $other);
    }

    public function test_a_position_moved_into_another_group_with_no_anchor_goes_at_the_end(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $other = self::createGroupWithPositions('X0 Y1', 'other_collection');

        self::requestJsonLd($client, 'PATCH', $other['X'], ['componentGroup' => $names['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('sortValue', 11);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10 X11', ['X' => $other['X']] + $names);
        self::assertGroupHoldsPositions($other['group'], 'Y1', $other);
    }

    public function test_a_position_moved_within_its_group_by_an_anchor_is_placed_by_the_same_rule_as_a_reorder(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1 C2');

        self::requestJsonLd($client, 'PATCH', $names['C'], ['before' => $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'C2 A3 B4', $names);
    }

    public function test_a_position_cannot_be_placed_relative_to_itself(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B1');

        self::requestJsonLd($client, 'PATCH', $names['A'], ['after' => $names['A']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'after');
        self::assertGroupHoldsPositions($names['group'], 'A0 B1', $names);
    }

    public function test_an_anonymous_user_cannot_reorder_a_group(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::sendOrder($client, $names['group'], [$names['B'], $names['A']]);

        self::assertResponseStatusCodeSame(401);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public function test_a_reorder_of_a_group_that_does_not_exist_is_a_404(): void
    {
        $client = self::loggedInClient();

        self::requestJsonLd($client, 'PATCH', '/_/component_groups/not-a-group/order', '{"componentPositions": []}');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_reorder_is_a_merge_patch(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'PATCH', '/_/component_groups/collection/order', ['componentPositions' => [$names['B'], $names['A']]]);

        self::assertResponseStatusCodeSame(200);
        self::assertIsArray(self::lastJson());
        self::assertOrderSortValues([[$names['B'], 10], [$names['A'], 11]]);
    }

    public function test_a_reorder_sent_as_json_ld_is_refused(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'PATCH', '/_/component_groups/collection/order', '{"componentPositions": []}', ['content-type' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(415);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
    }

    public function test_the_order_is_the_groups_own_iri_with_order_appended_under_the_route_import_prefix(): void
    {
        static::bootKernel(['routePrefix' => '/_api']);
        $client = static::createClient();
        $user = self::loginAsUser($client);
        $names = self::createGroupWithPositions('A0 B10');

        self::requestJsonLd($client, 'GET', '/_api/_/component_groups/collection');
        self::assertResponseStatusCodeSame(200);
        $group = self::jsonNode('@id');
        self::assertSame($names['group'], $group);

        self::requestJsonLd($client, 'GET', $group . '/order');
        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($group);
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 10]]);

        self::reissueToken($client, $user);
        self::sendOrder($client, $group, [$names['B'], $names['A']]);
        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($group);
        self::assertOrderSortValues([[$names['B'], 10], [$names['A'], 11]]);
    }

    public function test_the_sort_values_and_component_group_of_a_reorder_body_are_read_only_and_change_nothing(): void
    {
        $client = self::loggedInClient();
        $names = self::createGroupWithPositions('A0 B10');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::requestJsonLd($client, 'PATCH', '/_/component_groups/collection/order', [
            'componentGroup' => $other['group'],
            'sortValues' => [$names['B'] => 0, $names['A'] => 5],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertOrderIsOf($names['group']);
        self::assertOrderSortValues([[$names['A'], 0], [$names['B'], 10]]);
        self::assertJsonNodeSame('componentPositions', [$names['A'], $names['B']]);
        self::assertGroupHoldsPositions($names['group'], 'A0 B10', $names);
        self::assertGroupHoldsPositions($other['group'], 'X0', $other);
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

    private static function reissueToken(Client $client, User $user): void
    {
        $managed = self::getEntityManager()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $managed);
        self::addBearerToken($client, $managed);
    }

    private static function holdRestrictedComponent(string $positionIri): void
    {
        $component = new RestrictedComponent();
        self::updateResource($positionIri, static function (object $position) use ($component): void {
            \assert($position instanceof ComponentPosition);
            self::getEntityManager()->persist($component);
            $position->component = $component;
        });
    }
}
