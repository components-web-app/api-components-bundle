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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentGroupAllowedComponentUiTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    private const string DUMMY = '/component/dummy_components';

    private const array HERO_ONLY = [self::DUMMY => ['ui' => ['CwaComponentHero']]];

    private const array LARGE_ONLY = [self::DUMMY => ['styles' => ['style:large']]];

    public function test_a_component_group_stores_the_allowed_component_ui_and_returns_it(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $allowed = [self::DUMMY => ['ui' => [null, 'CwaComponentHero'], 'styles' => ['style:large']]];

        self::requestJsonLd($client, 'POST', '/_/component_groups', ['reference' => 'hero', 'location' => 'top', 'allowedComponentUi' => $allowed]);
        self::assertResponseStatusCodeSame(201);
        self::requestJsonLd($client, 'GET', (string) self::lastJson()['@id']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('allowedComponentUi', $allowed);
    }

    public function test_a_component_group_with_no_allowed_component_ui_has_a_null_one(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertNull(self::lastJson()['allowedComponentUi'] ?? null);
    }

    public function test_an_empty_allowed_component_ui_is_stored_as_null(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'PATCH', $group['group'], '{"allowedComponentUi": {}}');

        self::assertResponseStatusCodeSame(200);
        $stored = self::findByIri($group['group']);
        self::assertInstanceOf(ComponentGroup::class, $stored);
        self::assertNull($stored->allowedComponentUi);
    }

    #[DataProvider('malformedAllowedComponentUi')]
    public function test_a_malformed_allowed_component_ui_is_refused(string $allowedComponentUi): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);

        self::requestJsonLd($client, 'PATCH', $group['group'], \sprintf('{"allowedComponentUi": %s}', $allowedComponentUi));

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'allowedComponentUi');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAllowedComponentUi(): iterable
    {
        yield 'a list instead of an object' => ['[{"ui": ["X"]}]'];
        yield 'a key that is not an IRI' => ['{"dummy": {"ui": ["X"]}}'];
        yield 'a key that is the class name of a component' => ['{"Silverback\\\\ApiComponentsBundle\\\\Tests\\\\Functional\\\\TestBundle\\\\Entity\\\\DummyComponent": {"ui": ["X"]}}'];
        yield 'a key that is the collection IRI of a resource that is not a component' => ['{"/_/pages": {"ui": ["X"]}}'];
        yield 'a key that is an item IRI of a component' => ['{"/component/dummy_components/abc": {"ui": ["X"]}}'];
        yield 'a value that is a string' => ['{"/component/dummy_components": "X"}'];
        yield 'a value that is null' => ['{"/component/dummy_components": null}'];
        yield 'a value that is a list' => ['{"/component/dummy_components": ["X"]}'];
        yield 'an empty value' => ['{"/component/dummy_components": {}}'];
        yield 'a value with another key' => ['{"/component/dummy_components": {"ui": ["X"], "other": ["Y"]}}'];
        yield 'ui that is a string' => ['{"/component/dummy_components": {"ui": "X"}}'];
        yield 'ui that is an object' => ['{"/component/dummy_components": {"ui": {"a": "X"}}}'];
        yield 'ui that holds a number' => ['{"/component/dummy_components": {"ui": [1]}}'];
        yield 'ui that holds a list' => ['{"/component/dummy_components": {"ui": [["X"]]}}'];
        yield 'an empty ui' => ['{"/component/dummy_components": {"ui": []}}'];
        yield 'styles that is a string' => ['{"/component/dummy_components": {"styles": "style:large"}}'];
        yield 'styles that is an object' => ['{"/component/dummy_components": {"styles": {"a": "style:large"}}}'];
        yield 'styles that holds a null' => ['{"/component/dummy_components": {"styles": [null]}}'];
        yield 'styles that holds a number' => ['{"/component/dummy_components": {"styles": [1]}}'];
        yield 'empty styles' => ['{"/component/dummy_components": {"styles": []}}'];
    }

    public function test_a_component_whose_ui_is_not_allowed_cannot_be_placed_in_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $component = self::createComponentWithUi('CwaComponentBanner');

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertJsonNodeSame('violations[0].message', 'The UI `CwaComponentBanner` is not allowed for `/component/dummy_components` in the component group `collection`.');
        self::assertSame(0, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_whose_ui_is_allowed_can_be_placed_in_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $component = self::createComponentWithUi('CwaComponentHero');

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_position_cannot_be_given_a_component_whose_ui_is_not_allowed(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => [null]]]);
        $component = self::createComponentWithUi('CwaComponentBanner');

        self::requestJsonLd($client, 'PATCH', $group['positions'][0], ['component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_a_position_holding_a_component_whose_ui_is_not_allowed_cannot_be_moved_into_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $other = self::createGroupWithComponentIris(1, 'other_collection');

        self::requestJsonLd($client, 'PATCH', $other['positions'][0], ['componentGroup' => $group['group']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertJsonNodeSame('violations[0].message', 'The UI `null` is not allowed for `/component/dummy_components` in the component group `collection`.');
    }

    public function test_a_component_whose_ui_is_not_allowed_cannot_be_created_straight_into_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'POST', self::DUMMY, ['uiComponent' => 'CwaComponentBanner', 'componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0].component');
        self::assertSame(0, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_whose_ui_is_allowed_can_be_created_straight_into_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'POST', self::DUMMY, ['uiComponent' => 'CwaComponentHero', 'componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, self::countEntities(ComponentPosition::class));
    }

    public function test_a_component_with_a_style_that_is_not_allowed_cannot_be_placed_in_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::LARGE_ONLY);
        $component = self::createComponentWithUi(null, ['style:large', 'style:dark', 'style:wide']);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertJsonNodeSame('violations[0].message', 'The styles `style:dark, style:wide` are not allowed for `/component/dummy_components` in the component group `collection`.');
    }

    public function test_a_component_with_only_allowed_styles_can_be_placed_in_the_group(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::LARGE_ONLY);
        $component = self::createComponentWithUi('AnyUi', ['style:large']);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_component_with_no_styles_can_be_placed_in_a_group_that_restricts_styles(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::LARGE_ONLY);
        $component = self::createComponentWithUi(null);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_group_that_lists_no_ui_allows_any_ui(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::LARGE_ONLY);
        $component = self::createComponentWithUi('CwaComponentBanner');

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_group_that_lists_no_styles_allows_any_styles(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $component = self::createComponentWithUi('CwaComponentHero', ['style:anything']);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_component_type_the_group_does_not_list_is_unrestricted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], ['/component/dummy_publishable_components' => ['ui' => ['CwaComponentHero'], 'styles' => ['style:large']]]);
        $component = self::createComponentWithUi('CwaComponentBanner', ['style:dark']);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_the_primary_ui_is_refused_when_null_is_not_listed(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $component = self::createComponentWithUi(null);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_the_primary_ui_is_allowed_when_null_is_listed(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => [null, 'CwaComponentHero']]]);
        $component = self::createComponentWithUi(null);

        self::requestJsonLd($client, 'POST', '/_/component_positions', ['componentGroup' => $group['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_the_fallback_of_a_dynamic_position_must_have_an_allowed_ui(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);
        $component = self::createComponentWithUi('CwaComponentBanner');

        self::requestJsonLd($client, 'POST', '/_/component_positions', [
            'componentGroup' => $group['group'],
            'component' => $component,
            'pageDataProperty' => 'component',
            'pageDataClass' => PageDataWithComponent::class,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_changing_a_components_ui_to_one_its_group_does_not_allow_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setComponentUi($group['components'][0], 'CwaComponentHero');
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiComponent' => 'CwaComponentBanner']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'uiComponent');
        self::assertJsonNodeSame('violations[0].message', 'The UI `CwaComponentBanner` is not allowed for `/component/dummy_components` in the component group `collection`.');
    }

    public function test_changing_a_components_styles_to_ones_its_group_does_not_allow_is_refused(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setAllowedComponentUi($group['group'], self::LARGE_ONLY);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiClassNames' => ['style:large', 'style:dark']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'uiClassNames');
        self::assertJsonNodeSame('violations[0].message', 'The styles `style:dark` are not allowed for `/component/dummy_components` in the component group `collection`.');
    }

    public function test_changing_a_components_ui_to_one_its_group_allows_is_accepted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => [null, 'CwaComponentHero'], 'styles' => ['style:large']]]);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiComponent' => 'CwaComponentHero', 'uiClassNames' => ['style:large']]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('uiComponent', 'CwaComponentHero');
    }

    public function test_a_component_in_two_groups_must_have_a_ui_both_allow(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => [null, 'CwaComponentHero']]]);
        $other = self::createGroupWithComponentIris(0, 'other_collection');
        self::setAllowedComponentUi($other['group'], [self::DUMMY => ['ui' => [null, 'CwaComponentBanner']]]);
        self::addPositionTo($other['group'], $group['components'][0], 0);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiComponent' => 'CwaComponentHero']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'uiComponent');
        self::assertJsonNodeSame('violations[0].message', 'The UI `CwaComponentHero` is not allowed for `/component/dummy_components` in the component group `other_collection`.');
    }

    public function test_a_new_draft_is_checked_against_its_published_components_groups(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        $published = self::addPublishedComponentToGroup($group['group']);
        self::setAllowedComponentUi($group['group'], ['/component/dummy_publishable_components' => ['ui' => [null]]]);

        self::requestJsonLd($client, 'PATCH', $published, ['uiComponent' => 'CwaComponentBanner']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'uiComponent');
        self::assertSame(1, self::countEntities(DummyPublishableComponent::class));
    }

    public function test_an_existing_draft_is_checked_against_its_published_components_groups(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        $pair = self::addPublishedComponentWithDraftTo($group['group']);
        self::setAllowedComponentUi($group['group'], ['/component/dummy_publishable_components' => ['ui' => [null]]]);

        self::requestJsonLd($client, 'PATCH', $pair['draft'], ['uiComponent' => 'CwaComponentBanner']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'uiComponent');
    }

    public function test_a_restriction_its_groups_components_already_break_can_be_saved(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setComponentUi($group['components'][0], 'CwaComponentBanner', ['style:dark']);

        self::requestJsonLd($client, 'PATCH', $group['group'], ['allowedComponentUi' => [self::DUMMY => ['ui' => ['CwaComponentHero'], 'styles' => ['style:large']]]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('componentPositions', 1);
    }

    public function test_a_component_that_breaks_its_groups_restriction_is_still_listed(): void
    {
        $client = static::createClient();
        $group = self::createGroupWithComponentIris(1);
        self::setComponentUi($group['components'][0], 'CwaComponentBanner');
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'GET', $group['group']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentPositions', $group['positions']);
    }

    public function test_a_component_that_breaks_its_groups_restriction_can_be_saved_with_its_ui_unchanged(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setComponentUi($group['components'][0], 'CwaComponentBanner', ['style:dark']);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => ['CwaComponentHero'], 'styles' => ['style:large']]]);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiComponent' => 'CwaComponentBanner', 'uiClassNames' => ['style:dark']]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_that_breaks_its_groups_ui_restriction_can_change_only_its_styles(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::setComponentUi($group['components'][0], 'CwaComponentBanner', ['style:dark']);
        self::setAllowedComponentUi($group['group'], [self::DUMMY => ['ui' => ['CwaComponentHero'], 'styles' => ['style:large']]]);

        self::requestJsonLd($client, 'PATCH', $group['components'][0], ['uiClassNames' => ['style:large']]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_position_of_a_component_that_breaks_its_groups_restriction_can_be_re_sorted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(2);
        self::setComponentUi($group['components'][0], 'CwaComponentBanner');
        self::setAllowedComponentUi($group['group'], self::HERO_ONLY);

        self::requestJsonLd($client, 'PATCH', $group['positions'][0], ['after' => $group['positions'][1]]);

        self::assertResponseStatusCodeSame(200);
        self::assertSortValues([$group['positions'][1] => 1, $group['positions'][0] => 2]);
    }

    private static function addPublishedComponentToGroup(string $groupIri): string
    {
        $group = self::findByIri($groupIri);
        self::assertInstanceOf(ComponentGroup::class, $group);
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));
        self::persistAndFlush($published, self::newComponentPosition($group, $published, 0));

        return self::iriOf($published);
    }
}
