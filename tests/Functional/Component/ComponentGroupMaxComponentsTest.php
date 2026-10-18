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

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentGroupMaxComponentsTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    private const string FULL = 'The component group `collection` is full: it holds at most 1 component.';

    public function test_a_component_group_is_created_with_a_maximum_number_of_components(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        self::requestJsonLd($client, 'POST', '/_/component_groups', '{"reference": "header_button", "location": "header", "maxComponents": 1}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component_group.schema.json');
        self::assertJsonNodeSame('maxComponents', 1);
    }

    public function test_the_maximum_number_of_components_is_readable_with_the_component_group(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('maxComponents', 1);
    }

    public function test_a_component_group_with_no_maximum_has_a_null_max_components(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(1);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull(self::lastJson()['maxComponents'] ?? null);
    }

    #[DataProvider('maxComponentsValues')]
    public function test_the_maximum_number_of_components_must_be_a_positive_integer_or_null(string $maxComponents, int $status): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'PATCH', $group['group'], \sprintf('{"maxComponents": %s}', $maxComponents));

        self::assertResponseStatusCodeSame($status);
    }

    public static function maxComponentsValues(): iterable
    {
        yield 'zero' => ['0', 422];
        yield 'negative' => ['-1', 422];
        yield 'one' => ['1', 200];
        yield 'null' => ['null', 200];
    }

    public function test_a_component_cannot_be_added_to_a_full_component_group(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentGroup');
        self::assertJsonNodeSame('violations[0].message', self::FULL);
        self::assertSame(1, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_can_be_added_to_a_component_group_below_its_maximum(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 2);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_component_group_with_no_maximum_takes_any_number_of_components(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(5);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_dynamic_page_data_position_cannot_be_added_to_a_full_component_group(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'POST', '/_/component_positions', [
            'componentGroup' => $group['group'],
            'pageDataProperty' => 'component',
            'pageDataClass' => PageDataWithComponent::class,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].message', self::FULL);
    }

    public function test_a_dynamic_page_data_position_counts_towards_the_maximum(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(0);
        self::addPositionTo($group['group'], null, 0, 'component');
        self::setMaxComponents($group['group'], 1);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
    }

    public function test_a_published_component_with_a_draft_counts_once(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(0);
        self::addPublishedComponentWithDraftTo($group['group']);
        self::setMaxComponents($group['group'], 2);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_position_hidden_by_the_allowed_components_does_not_count_towards_the_maximum(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::addRestrictedComponentPosition($group['group'], 5);
        self::setAllowedComponent($group['group'], '/component/dummy_components');
        self::setMaxComponents($group['group'], 2);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_component_cannot_be_created_straight_into_a_full_component_group(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0].componentGroup');
        self::assertJsonNodeSame('violations[0].message', self::FULL);
        self::assertSame(1, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_can_be_created_straight_into_a_component_group_below_its_maximum(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 2);

        self::requestJsonLd($client, 'POST', '/component/dummy_components', ['componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_in_a_full_component_group_can_still_be_edited(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], '{"uiComponent": "Another"}');

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_position_cannot_be_moved_into_a_full_component_group(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 1);
        $other = self::createGroupWithComponentIris(1, 'other_collection');

        self::requestJsonLd($client, 'PATCH', $other['positions'][0], ['componentGroup' => $group['group']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].message', self::FULL);
    }

    public function test_a_position_can_be_moved_into_a_component_group_below_its_maximum(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(1);
        self::setMaxComponents($group['group'], 2);
        $other = self::createGroupWithComponentIris(1, 'other_collection');

        self::requestJsonLd($client, 'PATCH', $other['positions'][0], ['componentGroup' => $group['group']]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_position_can_be_moved_within_a_full_component_group(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(2);
        self::setMaxComponents($group['group'], 2);

        self::requestJsonLd($client, 'PATCH', $group['positions'][0], ['after' => $group['positions'][1]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSortValues([$group['positions'][1] => 1, $group['positions'][0] => 2]);
    }

    public function test_lowering_the_maximum_below_the_components_a_group_holds_keeps_them_all(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(3);

        self::requestJsonLd($client, 'PATCH', $group['group'], '{"maxComponents": 1}');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('maxComponents', 1);
        self::assertJsonNodeCount('componentPositions', 3);
        self::assertSame(3, self::countEntities(ComponentPosition::class));
        self::assertSame(3, self::countEntities(DummyComponent::class));
    }

    public function test_a_group_holding_more_components_than_its_maximum_takes_no_more(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(3);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $group['components'][0]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].message', self::FULL);
    }

    public function test_a_position_in_a_group_over_its_maximum_can_still_be_moved_within_it(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $group = self::createGroupWithComponentIris(3);
        self::setMaxComponents($group['group'], 1);

        self::requestJsonLd($client, 'PATCH', $group['positions'][2], ['before' => $group['positions'][0]]);

        self::assertResponseStatusCodeSame(200);
    }
}
