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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithParentTypedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ComponentMoveTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ComponentSiteTrait;

    public function test_a_position_moved_to_another_group_is_the_same_position_placed_after_its_anchor(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0 Y1', 'other_collection');
        $component = self::componentOf($names['A']);

        self::move($client, $component, ['componentPosition' => $names['A']], ['componentGroup' => $other['group'], 'after' => $other['X']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPosition', $names['A']);
        self::assertJsonNodeSame('from', ['componentPosition' => $names['A']]);
        self::assertJsonNodeSame('to', ['componentGroup' => $other['group'], 'after' => $other['X']]);
        self::assertGroupHoldsPositions($other['group'], 'X0 A1 Y2', $other + ['A' => $names['A']]);
        self::assertGroupHoldsPositions($names['group'], 'B1', $names);
        self::assertSame($component, self::componentOf($names['A']));
    }

    public function test_a_position_moved_to_another_group_with_no_anchor_goes_at_the_end(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0 Y5', 'other_collection');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($other['group'], 'X0 Y5 A6', $other + ['A' => $names['A']]);
    }

    public function test_a_position_moved_to_another_group_purges_and_publishes_the_position_and_both_groups(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0', 'other_collection');
        HubStub::clearPublished();

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged($names['A']);
        self::assertCacheTagPurged($names['group']);
        self::assertCacheTagPurged($other['group']);
        self::assertPositionUpdatesPublishedForExactly([$names['A']]);
        self::assertMercurePublishedFor($names['A']);
    }

    public function test_a_move_within_one_group_reorders_it_by_the_anchor(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1 C2');

        self::move($client, self::componentOf($names['C']), ['componentPosition' => $names['C']], ['componentGroup' => $names['group'], 'before' => $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPosition', $names['C']);
        self::assertGroupHoldsPositions($names['group'], 'C2 A3 B4', $names);
        self::assertPositionUpdatesPublishedForExactly([$names['A'], $names['B'], $names['C']]);
    }

    public function test_a_position_moved_into_a_page_data_slot_sets_the_property_and_removes_only_the_position(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $site = self::pageDataSite();
        self::emptySlot($site['pageData'], 'component');
        $component = self::componentOf($names['A']);
        HubStub::clearPublished();

        self::move($client, $component, ['componentPosition' => $names['A']], ['pageData' => $site['pageData'], 'property' => 'component']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNull('componentPosition');
        self::assertJsonNodeSame('to', ['pageData' => $site['pageData'], 'property' => 'component']);
        self::assertSlotHolds($site['pageData'], 'component', $component);
        self::assertResourceNotExists($names['A']);
        self::assertResourceExists($component);
        self::assertGroupHoldsPositions($names['group'], 'B1', $names);
        self::assertCacheTagPurged($names['A']);
        self::assertCacheTagPurged($site['pageData']);
        self::assertMercurePublishedFor($names['A']);
    }

    public function test_a_component_moved_from_a_page_data_slot_into_a_group_gets_a_new_position_after_its_anchor(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $site = self::pageDataSite();
        HubStub::clearPublished();

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group'], 'after' => $names['A']]);

        self::assertResponseStatusCodeSame(200);
        $position = self::jsonNode('componentPosition');
        self::assertIsString($position);
        self::assertSame($site['component'], self::componentOf($position));
        self::assertGroupHoldsPositions($names['group'], 'A0 N1 B2', $names + ['N' => $position]);
        self::assertSlotHolds($site['pageData'], 'component', null);
        self::assertDynamicPositionIsUnchanged($site['dynamicPosition']);
        self::assertCacheTagPurged($site['pageData']);
        self::assertCacheTagPurged($names['group']);
        self::assertMercurePublishedFor($position);
    }

    public function test_a_component_moved_from_a_page_data_slot_into_a_group_with_no_anchor_goes_at_the_end(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B3');
        $site = self::pageDataSite();

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'A0 B3 N4', $names + ['N' => (string) self::jsonNode('componentPosition')]);
    }

    public function test_a_component_moved_between_slots_of_two_page_data_resources_sets_one_and_nulls_the_other(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $other = self::addPageDataUsing($site['page'], '/other-page-data');
        HubStub::clearPublished();

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['pageData' => $other, 'property' => 'component']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeIsNull('componentPosition');
        self::assertSlotHolds($other, 'component', $site['component']);
        self::assertSlotHolds($site['pageData'], 'component', null);
        self::assertDynamicPositionIsUnchanged($site['dynamicPosition']);
        self::assertCacheTagPurged($site['pageData']);
        self::assertCacheTagPurged($other);
    }

    public function test_a_component_moved_to_a_parent_typed_slot_of_another_page_data_class_is_held_there(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $other = self::addParentTypedPageDataUsing($site['page']);

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['pageData' => $other, 'property' => 'component']);

        self::assertResponseStatusCodeSame(200);
        self::assertSlotHolds($other, 'component', $site['component']);
        self::assertCacheTagPurged($other);
        self::assertMercurePublishedFor($other);
    }

    public function test_a_component_moved_out_of_a_slot_of_a_mercure_page_data_resource_publishes_it(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $other = self::addParentTypedPageDataUsing($site['page'], $site['component']);
        self::emptySlot($site['pageData'], 'component');
        $names = self::createGroupWithPositions('A0');
        HubStub::clearPublished();

        self::move($client, $site['component'], ['pageData' => $other, 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSlotHolds($other, 'component', null);
        self::assertCacheTagPurged($other);
        self::assertMercurePublishedFor($other);
        self::assertMercurePublishedFor((string) self::jsonNode('componentPosition'));
    }

    public function test_moving_to_the_slot_it_comes_from_is_refused(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $slot = ['pageData' => $site['pageData'], 'property' => 'component'];

        self::move($client, $site['component'], $slot, $slot);

        self::assertViolation('to', 'The component is already held by this property.');
        self::assertSlotHolds($site['pageData'], 'component', $site['component']);
    }

    public function test_moving_from_a_dynamic_position_is_refused(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $names = self::createGroupWithPositions('A0');

        self::move($client, $site['component'], ['componentPosition' => $site['dynamicPosition']], ['componentGroup' => $names['group']]);

        self::assertViolation('from.componentPosition', 'A dynamic position cannot be moved: move the page data property instead.');
        self::assertDynamicPositionIsUnchanged($site['dynamicPosition']);
    }

    public function test_moving_from_a_position_with_no_component_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $empty = self::addPositionTo($names['group'], null, 5);
        $component = self::createDummyComponent();

        self::move($client, $component, ['componentPosition' => $empty], ['componentGroup' => $names['group']]);

        self::assertViolation('from.componentPosition', 'The position holds no component.');
    }

    public function test_moving_from_a_position_holding_another_component_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, self::componentOf($names['B']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertViolation('from.componentPosition', 'The position does not hold this component.');
        self::assertGroupHoldsPositions($names['group'], 'A0 B1', $names);
    }

    public function test_moving_from_an_empty_slot_is_refused(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        self::emptySlot($site['pageData'], 'component');
        $names = self::createGroupWithPositions('A0');

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertViolation('from.property', 'The property holds no component.');
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public function test_moving_from_a_slot_holding_another_component_is_refused(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $names = self::createGroupWithPositions('A0');

        self::move($client, self::createDummyComponent(), ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertViolation('from.property', 'The property does not hold this component.');
        self::assertSlotHolds($site['pageData'], 'component', $site['component']);
    }

    public function test_moving_into_a_slot_that_already_holds_a_component_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $site = self::pageDataSite();

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['pageData' => $site['pageData'], 'property' => 'component']);

        self::assertViolation('to.property', 'The property already holds a component.');
        self::assertSlotHolds($site['pageData'], 'component', $site['component']);
        self::assertResourceExists($names['A']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function propertiesThatCannotHoldADummyComponent(): iterable
    {
        yield 'a property of another component class' => ['publishableComponent'];
        yield 'an association that is not a component' => ['page'];
        yield 'a property that does not exist' => ['nothing'];
    }

    #[DataProvider('propertiesThatCannotHoldADummyComponent')]
    public function test_moving_into_a_property_that_cannot_hold_the_component_is_refused(string $property): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $site = self::pageDataSite();
        self::emptySlot($site['pageData'], 'publishableComponent');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['pageData' => $site['pageData'], 'property' => $property]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'to.property');
        self::assertResourceExists($names['A']);
    }

    public function test_moving_from_a_property_that_is_not_a_component_property_is_refused(): void
    {
        $client = self::adminClient();
        $site = self::pageDataSite();
        $names = self::createGroupWithPositions('A0');

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'page'], ['componentGroup' => $names['group']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'from.property');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function unresolvableBodies(): iterable
    {
        yield 'a from that is not an object' => [['from' => 'nothing', 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from'];
        yield 'a from with neither shape' => [['from' => ['componentGroup' => '/_/component_groups/collection'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from'];
        yield 'a from with both shapes' => [['from' => ['componentPosition' => 'A', 'pageData' => '/page_data/x', 'property' => 'component'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from'];
        yield 'a from page data with no property' => [['from' => ['pageData' => '/page_data/x'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from'];
        yield 'a from position that does not exist' => [['from' => ['componentPosition' => '/_/component_positions/00000000-0000-0000-0000-000000000000'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from.componentPosition'];
        yield 'a from position that is not a position' => [['from' => ['componentPosition' => '/_/component_groups/collection'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from.componentPosition'];
        yield 'a from page data that is not page data' => [['from' => ['pageData' => '/_/component_groups/collection', 'property' => 'component'], 'to' => ['componentGroup' => '/_/component_groups/collection']], 'from.pageData'];
        yield 'no from' => [['to' => ['componentGroup' => '/_/component_groups/collection']], 'from'];
        yield 'no to' => [['from' => ['componentPosition' => 'A']], 'to'];
        yield 'a to with neither shape' => [['from' => ['componentPosition' => 'A'], 'to' => ['componentPosition' => 'A']], 'to'];
        yield 'a to with both shapes' => [['from' => ['componentPosition' => 'A'], 'to' => ['componentGroup' => '/_/component_groups/collection', 'pageData' => '/page_data/x', 'property' => 'component']], 'to'];
        yield 'a to page data with anchors' => [['from' => ['componentPosition' => 'A'], 'to' => ['pageData' => '/page_data/x', 'property' => 'component', 'after' => 'A']], 'to'];
        yield 'a to group that does not exist' => [['from' => ['componentPosition' => 'A'], 'to' => ['componentGroup' => '/_/component_groups/nothing']], 'to.componentGroup'];
        yield 'a to group that is not a group' => [['from' => ['componentPosition' => 'A'], 'to' => ['componentGroup' => 'A']], 'to.componentGroup'];
        yield 'a to page data that is not page data' => [['from' => ['componentPosition' => 'A'], 'to' => ['pageData' => '/_/component_groups/collection', 'property' => 'component']], 'to.pageData'];
        yield 'a to anchor that is not a string' => [['from' => ['componentPosition' => 'A'], 'to' => ['componentGroup' => '/_/component_groups/collection', 'after' => 5]], 'to.after'];
        yield 'a to property that is not a string' => [['from' => ['componentPosition' => 'A'], 'to' => ['pageData' => '/page_data/x', 'property' => 5]], 'to.property'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('unresolvableBodies')]
    public function test_a_body_that_does_not_resolve_is_refused_at_the_part_that_does_not(array $body, string $propertyPath): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $site = self::pageDataSite();
        $body = self::replacePlaceholders($body, ['A' => $names['A'], '/page_data/x' => $site['pageData']]);

        self::requestJsonLd($client, 'POST', self::componentOf($names['A']) . '/move', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $propertyPath);
        self::assertGroupHoldsPositions($names['group'], 'A0 B1', $names);
    }

    public function test_an_anchor_outside_the_target_group_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group'], 'after' => $names['B']]);

        self::assertViolation('to.after', \sprintf('The position `%s` is not in the component group `other_collection`.', $names['B']));
        self::assertGroupHoldsPositions($names['group'], 'A0 B1', $names);
        self::assertGroupHoldsPositions($other['group'], 'X0', $other);
    }

    public function test_both_anchors_are_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0 Y1', 'other_collection');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group'], 'after' => $other['X'], 'before' => $other['Y']]);

        self::assertViolation('to.before', 'Send either after or before, not both.');
    }

    public function test_moving_into_a_full_group_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        $other = self::createGroupWithPositions('X0', 'other_collection');
        self::setMaxComponents($other['group'], 1);

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertViolation('to.componentGroup', 'The component group `other_collection` is full: it holds at most 1 component.');
        self::assertGroupHoldsPositions($names['group'], 'A0 B1', $names);
    }

    public function test_moving_from_a_slot_into_a_full_group_is_refused(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        self::setMaxComponents($names['group'], 1);
        $site = self::pageDataSite();

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertViolation('to.componentGroup', 'The component group `collection` is full: it holds at most 1 component.');
        self::assertSlotHolds($site['pageData'], 'component', $site['component']);
    }

    public function test_a_move_within_a_full_group_is_allowed(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0 B1');
        self::setMaxComponents($names['group'], 2);

        self::move($client, self::componentOf($names['B']), ['componentPosition' => $names['B']], ['componentGroup' => $names['group'], 'before' => $names['A']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($names['group'], 'B1 A2', $names);
    }

    public function test_an_explicit_allow_only_component_cannot_be_moved_into_a_group_that_does_not_allow_it(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $restricted = self::addRestrictedComponentPosition($names['group'], 1);
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, self::componentOf($restricted), ['componentPosition' => $restricted], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'to.componentGroup');
        self::assertJsonNodeMatches('violations[0].message', '/must be specifically allowed within the collection other_collection/');
    }

    public function test_a_component_cannot_be_moved_into_a_group_inside_itself(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $component = self::componentOf($names['A']);
        $nested = self::addGroupOwnedBy($component, 'nested');

        self::move($client, $component, ['componentPosition' => $names['A']], ['componentGroup' => $nested]);

        self::assertViolation('to.componentGroup', 'The component cannot be placed in the component group `nested`: the group is inside the component.');
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public function test_a_move_by_the_drafts_iri_moves_the_published_component_and_stores_it(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $pair = self::addPublishedComponentWithDraftTo($names['group']);
        $site = self::pageDataSite();
        self::emptySlot($site['pageData'], 'publishableComponent');

        self::move($client, $pair['draft'], ['componentPosition' => $pair['position']], ['pageData' => $site['pageData'], 'property' => 'publishableComponent']);

        self::assertResponseStatusCodeSame(200);
        self::assertSlotHolds($site['pageData'], 'publishableComponent', $pair['published']);
        self::assertResourceNotExists($pair['position']);
        self::assertResourceExists($pair['draft']);
    }

    public function test_a_move_by_the_published_iri_moves_a_slot_holding_its_draft(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $pair = self::addPublishedComponentWithDraftTo($names['group']);
        $site = self::pageDataSite();
        self::fillSlotWithoutTheListener($site['pageData'], 'publishableComponent', $pair['draft']);
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, $pair['published'], ['pageData' => $site['pageData'], 'property' => 'publishableComponent'], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame($pair['published'], self::componentOf((string) self::jsonNode('componentPosition')));
        self::assertSlotHolds($site['pageData'], 'publishableComponent', null);
    }

    public function test_an_anonymous_caller_cannot_move_a_component(): void
    {
        $client = static::createClient();
        $names = self::createGroupWithPositions('A0');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(401);
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public function test_an_admin_granted_the_position_write_can_move_a_position(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $other = self::createGroupWithPositions('X0', 'other_collection');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertGroupHoldsPositions($other['group'], 'X0 A1', $other + ['A' => $names['A']]);
    }

    public function test_a_signed_in_user_without_the_publishable_permission_cannot_move_into_a_page_data_slot(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $names = self::createGroupWithPositions('A0');
        $site = self::pageDataSite(null);
        self::emptySlot($site['pageData'], 'component');

        self::move($client, self::componentOf($names['A']), ['componentPosition' => $names['A']], ['pageData' => $site['pageData'], 'property' => 'component']);

        self::assertResponseStatusCodeSame(403);
        self::assertResourceExists($names['A']);
        self::assertSlotHolds($site['pageData'], 'component', null);
    }

    public function test_a_signed_in_user_without_the_publishable_permission_cannot_move_out_of_a_page_data_slot(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $names = self::createGroupWithPositions('A0');
        $site = self::pageDataSite(null);

        self::move($client, $site['component'], ['pageData' => $site['pageData'], 'property' => 'component'], ['componentGroup' => $names['group']]);

        self::assertResponseStatusCodeSame(403);
        self::assertSlotHolds($site['pageData'], 'component', $site['component']);
        self::assertGroupHoldsPositions($names['group'], 'A0', $names);
    }

    public function test_an_admin_granted_the_page_data_write_can_move_into_its_slot(): void
    {
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $site = self::pageDataSite();
        self::emptySlot($site['pageData'], 'component');
        $component = self::componentOf($names['A']);

        self::move($client, $component, ['componentPosition' => $names['A']], ['pageData' => $site['pageData'], 'property' => 'component']);

        self::assertResponseStatusCodeSame(200);
        self::assertSlotHolds($site['pageData'], 'component', $component);
    }

    public function test_the_move_is_under_the_components_own_iri_with_the_route_import_prefix(): void
    {
        static::bootKernel(['routePrefix' => '/_api']);
        $client = self::adminClient();
        $names = self::createGroupWithPositions('A0');
        $other = self::createGroupWithPositions('X0', 'other_collection');
        $component = self::componentOf($names['A']);
        self::assertStringStartsWith('/_api/component/dummy_components/', $component);

        self::move($client, $component, ['componentPosition' => $names['A']], ['componentGroup' => $other['group']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPosition', $names['A']);
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    private static function move(Client $client, string $componentIri, array $from, array $to): ResponseInterface
    {
        return self::requestJsonLd($client, 'POST', $componentIri . '/move', ['from' => $from, 'to' => $to]);
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }

    private static function componentOf(string $positionIri): string
    {
        $position = self::findByIri($positionIri);
        self::assertInstanceOf(ComponentPosition::class, $position);

        return self::iriOf($position->component ?? self::fail('The position holds no component.'));
    }

    /**
     * @return array{page: string, pageData: string, component: string, publishableComponent: string, dynamicPosition: string, group: string}
     */
    private static function pageDataSite(?string $path = '/page-data'): array
    {
        $site = self::createPageDataWithRoute($path);

        return [
            'page' => self::iriOf($site['page']),
            'pageData' => self::iriOf($site['pageData']),
            'component' => self::iriOf($site['component']),
            'publishableComponent' => self::iriOf($site['publishableComponent']),
            'dynamicPosition' => self::iriOf($site['position']),
            'group' => self::iriOf($site['group']),
        ];
    }

    private static function addPageDataUsing(string $pageIri, string $path): string
    {
        $page = self::findByIri($pageIri);
        self::assertInstanceOf(Page::class, $page);
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        $route = self::newRoute($path)->setPageData($pageData);
        self::persistAndFlush($pageData, $route);

        return self::iriOf($pageData);
    }

    private static function addParentTypedPageDataUsing(string $pageIri, ?string $componentIri = null): string
    {
        $page = self::findByIri($pageIri);
        self::assertInstanceOf(Page::class, $page);
        $pageData = new PageDataWithParentTypedComponent();
        $pageData->page = $page;
        if (null !== $componentIri) {
            $component = self::findByIri($componentIri);
            self::assertInstanceOf(AbstractComponent::class, $component);
            $pageData->page = self::getEntityManager()->find(Page::class, $page->getId());
            $pageData->component = $component;
        }
        $route = self::newRoute('/parent-typed')->setPageData($pageData);
        self::persistAndFlush($pageData, $route);

        return self::iriOf($pageData);
    }

    private static function emptySlot(string $pageDataIri, string $property): void
    {
        self::updateResource($pageDataIri, static function (object $pageData) use ($property): void {
            $pageData->{$property} = null;
        });
    }

    private static function fillSlotWithoutTheListener(string $pageDataIri, string $property, string $componentIri): void
    {
        $pageData = self::findByIri($pageDataIri);
        $component = self::findByIri($componentIri);
        self::assertNotNull($pageData);
        self::assertInstanceOf(AbstractComponent::class, $component);
        $connection = self::getEntityManager()->getConnection();
        $metadata = self::getEntityManager()->getClassMetadata($pageData::class);
        $column = $metadata->getSingleAssociationJoinColumnName($property);
        $connection->update($metadata->getTableName(), [$column => (string) $component->getId()], ['id' => (string) $pageData->getId()]);
        self::getEntityManager()->clear();
        self::rebootKernel();
    }

    private static function addGroupOwnedBy(string $componentIri, string $reference): string
    {
        $owner = self::findByIri($componentIri);
        self::assertInstanceOf(AbstractComponent::class, $owner);
        $group = self::newComponentGroup($reference);
        $owner->addComponentGroup($group);
        self::persistAndFlush($group);

        return self::iriOf($group);
    }

    private static function assertSlotHolds(string $pageDataIri, string $property, ?string $componentIri): void
    {
        self::getEntityManager()->clear();
        $pageData = self::findByIri($pageDataIri);
        self::assertNotNull($pageData);
        $held = $pageData->{$property};
        self::assertSame($componentIri, null === $held ? null : self::iriOf($held));
    }

    private static function assertDynamicPositionIsUnchanged(string $positionIri): void
    {
        self::getEntityManager()->clear();
        $position = self::findByIri($positionIri);
        self::assertInstanceOf(ComponentPosition::class, $position);
        self::assertSame('component', $position->pageDataProperty);
        self::assertNull($position->component);
        self::assertInstanceOf(ComponentGroup::class, $position->componentGroup);
    }

    private static function assertJsonNodeIsNull(string $path): void
    {
        self::assertArrayHasKey($path, self::lastJson());
        self::assertNull(self::lastJson()[$path]);
    }

    private static function assertViolation(string $propertyPath, string $message): void
    {
        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', $propertyPath);
        self::assertJsonNodeSame('violations[0].message', $message);
    }

    private static function assertMercurePublishedFor(string $iri): void
    {
        $topics = [];
        foreach (self::mercureUpdatesInLastRequest() as $update) {
            \assert($update instanceof Update);
            foreach ($update->getTopics() as $topic) {
                $topics[] = parse_url($topic, \PHP_URL_PATH) ?: $topic;
            }
        }
        self::assertContains($iri, $topics, \sprintf('No Mercure update was published for %s. Topics: %s', $iri, implode(', ', $topics)));
    }

    /**
     * @param array<mixed>          $body
     * @param array<string, string> $replacements
     *
     * @return array<mixed>
     */
    private static function replacePlaceholders(array $body, array $replacements): array
    {
        foreach ($body as $key => $value) {
            if (\is_array($value)) {
                $body[$key] = self::replacePlaceholders($value, $replacements);
            } elseif (\is_string($value) && isset($replacements[$value])) {
                $body[$key] = $replacements[$value];
            }
        }

        return $body;
    }
}
