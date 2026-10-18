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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentCloneTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    public function test_a_component_placed_in_another_group_is_cloned_by_its_iri_without_a_copy(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(1, 'source');
        $target = self::createGroupWithComponentIris(1);
        $component = $source['components'][0];

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('component', $component);
        self::assertJsonNodeSame('componentGroup', $target['group']);
        self::assertSame(2, self::countEntities(DummyComponent::class));
        self::assertComponentIsIn([$source['group'], $target['group']], $component);
    }

    #[DataProvider('clonePlacements')]
    public function test_a_clone_is_placed_at_its_anchor(string $anchor, string $at, string $expected): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(1, 'source');
        $names = self::createGroupWithPositions('A0 B1 C10');

        self::postPosition($client, ['componentGroup' => $names['group'], 'component' => $source['components'][0], $anchor => $names[$at]]);

        self::assertResponseStatusCodeSame(201);
        $names['N'] = self::jsonNode('@id');
        self::assertGroupHoldsPositions($names['group'], $expected, $names);
    }

    public static function clonePlacements(): iterable
    {
        yield 'after a position with no room after it' => ['after', 'A', 'A0 N1 B2 C10'];
        yield 'after a position with room after it' => ['after', 'B', 'A0 B1 N2 C10'];
        yield 'before the first position' => ['before', 'A', 'N7 A8 B9 C10'];
        yield 'before a position with room before it' => ['before', 'C', 'A0 B1 N2 C10'];
    }

    public function test_a_clone_of_a_type_the_target_group_does_not_allow_is_a_422(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(1, 'source');
        $target = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($target['group'], '/component/restricted_components');

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $source['components'][0]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', '');
        self::assertJsonNodeSame('violations[0].message', 'The IRI `/component/dummy_components` is not permitted to be added to the collection `collection`. Allowed IRIs: /component/restricted_components');
        self::assertComponentIsIn([$source['group']], $source['components'][0]);
    }

    public function test_a_clone_of_an_explicit_allow_only_type_into_a_group_that_does_not_list_it_is_a_422(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(0, 'source');
        self::setAllowedComponent($source['group'], '/component/restricted_components');
        $component = self::componentOf(self::addRestrictedComponentPosition($source['group'], 0));
        $target = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', '');
        self::assertJsonNodeSame('violations[0].message', 'The IRI `/component/restricted_components` must be specifically allowed within the collection collection');
        self::assertComponentIsIn([$source['group']], $component);
    }

    public function test_a_clone_of_an_explicit_allow_only_type_into_a_group_that_lists_it_is_created(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(0, 'source');
        self::setAllowedComponent($source['group'], '/component/restricted_components');
        $component = self::componentOf(self::addRestrictedComponentPosition($source['group'], 0));
        $target = self::createGroupWithComponentIris(0);
        self::setAllowedComponent($target['group'], '/component/restricted_components');

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $component]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, self::countEntities(RestrictedComponent::class));
        self::assertComponentIsIn([$source['group'], $target['group']], $component);
    }

    public function test_a_clone_into_a_group_at_its_maximum_is_a_422(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(1, 'source');
        $target = self::createGroupWithComponentIris(1);
        self::setMaxComponents($target['group'], 1);

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $source['components'][0]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentGroup');
        self::assertJsonNodeSame('violations[0].message', 'The component group `collection` is full: it holds at most 1 component.');
        self::assertComponentIsIn([$source['group']], $source['components'][0]);
    }

    public function test_the_same_component_can_be_placed_twice_in_one_group(): void
    {
        $client = self::loggedInClient();
        $group = self::createGroupWithComponentIris(1);
        $component = $group['components'][0];

        self::postPosition($client, ['componentGroup' => $group['group'], 'component' => $component, 'after' => $group['positions'][0]]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('component', $component);
        self::assertSame(1, self::countEntities(DummyComponent::class));
        self::assertSame(2, self::countEntities(ComponentPosition::class));
        self::assertComponentIsIn([$group['group'], $group['group']], $component);
    }

    public function test_a_published_component_with_a_draft_is_cloned_by_its_iri_without_a_copy(): void
    {
        $client = self::loggedInClient();
        $source = self::createGroupWithComponentIris(0, 'source');
        $pair = self::addPublishedComponentWithDraftTo($source['group']);
        $target = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $pair['published']]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(2, self::countEntities(DummyPublishableComponent::class));
        self::assertComponentIsIn([$source['group'], $target['group']], $pair['published']);
        self::assertComponentIsIn([], $pair['draft']);
    }

    public function test_a_clone_by_a_drafts_iri_places_its_published_component_without_a_copy(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $source = self::createGroupWithComponentIris(0, 'source');
        $pair = self::addPublishedComponentWithDraftTo($source['group']);
        $target = self::createGroupWithComponentIris(0);

        self::postPosition($client, ['componentGroup' => $target['group'], 'component' => $pair['draft']]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('component', $pair['draft']);
        self::assertSame(2, self::countEntities(DummyPublishableComponent::class));
        self::assertComponentIsIn([$source['group'], $target['group']], $pair['published']);
        self::assertComponentIsIn([], $pair['draft']);
    }

    public function test_a_component_placed_in_a_group_is_cloned_into_a_page_data_property_by_its_iri_without_a_copy(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $source = self::createGroupWithComponentIris(1, 'source');
        $site = self::createPageDataWithRoute('/page-data');
        $pageData = self::iriOf($site['pageData']);
        $component = $source['components'][0];

        self::requestJsonLd($client, 'PATCH', $pageData, ['component' => $component]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('component', $component);
        self::assertSame(2, self::countEntities(DummyComponent::class));
        $reloaded = self::findByIri($pageData);
        self::assertInstanceOf(PageDataWithComponent::class, $reloaded);
        self::assertSame($component, self::iriOf($reloaded->component ?? self::fail('The page data holds no component.')));
        self::assertComponentIsIn([$source['group']], $component);
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

    private static function componentOf(string $positionIri): string
    {
        $position = self::findByIri($positionIri);
        self::assertInstanceOf(ComponentPosition::class, $position);
        self::assertInstanceOf(AbstractComponent::class, $position->component);

        return self::iriOf($position->component);
    }

    /**
     * @param list<string> $groups
     */
    private static function assertComponentIsIn(array $groups, string $componentIri): void
    {
        $component = self::findByIri($componentIri);
        self::assertInstanceOf(AbstractComponent::class, $component);
        $positions = self::getEntityManager()->getRepository(ComponentPosition::class)->findBy(['component' => $component], ['sortValue' => 'ASC', 'id' => 'ASC']);
        $held = array_map(static fn (ComponentPosition $position): string => self::iriOf($position->componentGroup), $positions);
        sort($held);
        sort($groups);
        self::assertSame($groups, $held);
    }
}
