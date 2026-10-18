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
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheTagsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class ComponentPositionCycleTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use CacheTagsTrait;
    use ComponentSiteTrait;
    use ManifestTrait;

    public function test_a_component_cannot_be_placed_in_one_of_its_own_component_groups(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::postPosition($client, ['componentGroup' => $site['nested']['group'], 'component' => $site['component']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
        self::assertJsonNodeSame('violations[0].message', 'The component cannot be placed in the component group `nested`: the group is inside the component.');
    }

    public function test_a_component_cannot_be_placed_in_a_component_group_further_down_its_own_subtree(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::postPosition($client, ['componentGroup' => $site['deeper']['group'], 'component' => $site['component']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_the_same_component_can_be_placed_in_two_sibling_containers(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();
        $sibling = self::addGroupHoldingAComponent($site['page'], 'sibling');
        $siblingInner = self::addGroupHoldingAComponent($sibling['component'], 'sibling_inner');

        self::postPosition($client, ['componentGroup' => $siblingInner['group'], 'component' => $site['nested']['component']]);

        self::assertResponseStatusCodeSame(201);
    }

    public function test_a_position_cannot_be_changed_to_hold_a_component_whose_subtree_holds_the_position(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::requestJsonLd($client, 'PATCH', $site['deeper']['position'], ['component' => $site['component']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_a_position_cannot_be_moved_into_a_component_group_inside_the_component_it_holds(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::requestJsonLd($client, 'PATCH', $site['position'], ['componentGroup' => $site['deeper']['group']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_a_component_group_cannot_be_given_an_owner_from_inside_its_own_subtree(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::requestJsonLd($client, 'PATCH', $site['group'], ['components' => [$site['nested']['component']]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'components');
        self::assertJsonNodeSame('violations[0].message', 'The component group `manifest-tag-group` cannot be owned by a component inside it.');
    }

    public function test_a_component_group_can_be_given_an_owner_from_outside_its_subtree(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();
        $sibling = self::addGroupHoldingAComponent($site['page'], 'sibling');

        self::requestJsonLd($client, 'PATCH', $site['deeper']['group'], ['components' => [$site['nested']['component'], $sibling['component']]]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_cannot_be_written_with_a_position_inside_its_own_subtree(): void
    {
        $client = self::loggedInClient();
        $site = self::createNestedSite();

        self::requestJsonLd($client, 'PATCH', $site['component'], ['componentPositions' => [['componentGroup' => $site['deeper']['group']]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'componentPositions[0].component');
    }

    public function test_a_draft_cannot_be_placed_inside_its_published_components_subtree(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createNestedSite();
        $published = self::createRoutedPageWhoseGroupHoldsAPublishedComponentWithADraft('/published-route');
        $publishedNested = self::addGroupHoldingAComponent($published['published'], 'published_nested');

        self::postPosition($client, ['componentGroup' => $publishedNested['group'], 'component' => $published['draft']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'component');
    }

    public function test_a_component_in_a_cycle_already_in_the_database_can_be_read_anonymously(): void
    {
        $client = static::createClient();
        $site = self::createNestedSite();
        self::addPositionTo($site['deeper']['group'], $site['component'], 0);

        self::requestJsonLd($client, 'GET', $site['nested']['component']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_component_in_a_cycle_already_in_the_database_can_be_read_by_an_admin(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createNestedSite();
        self::addPositionTo($site['deeper']['group'], $site['component'], 0);

        self::requestJsonLd($client, 'GET', $site['nested']['component']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_manifest_of_a_page_holding_a_cycle_already_in_the_database_is_built(): void
    {
        $client = static::createClient();
        $site = self::createNestedSite();
        self::addPositionTo($site['deeper']['group'], $site['component'], 0);

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//my-route');

        self::assertResponseStatusCodeSame(200);
        self::assertManifestDepthContains(0, $site['deeper']['group']);
    }

    public function test_a_write_inside_a_cycle_already_in_the_database_purges_the_manifest(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createNestedSite();
        self::addPositionTo($site['deeper']['group'], $site['component'], 0);

        self::requestJsonLd($client, 'PATCH', $site['deeper']['group'], '{"location": "moved"}');

        self::assertResponseStatusCodeSame(200);
        self::assertCacheTagPurged(self::manifestCacheTag($site['page']));
    }

    /**
     * @return array{page: string, group: string, component: string, position: string, nested: array{group: string, component: string, position: string}, deeper: array{group: string, component: string, position: string}}
     */
    private static function createNestedSite(): array
    {
        $created = self::createRoutedPageWithComponent('/my-route');
        $site = [
            'page' => self::iriOf($created['page']),
            'group' => self::iriOf($created['group']),
            'component' => self::iriOf($created['component']),
            'position' => self::iriOf($created['position']),
        ];
        $site['nested'] = self::addGroupHoldingAComponent($site['component'], 'nested');
        $site['deeper'] = self::addGroupHoldingAComponent($site['nested']['component'], 'deeper');

        return $site;
    }

    /**
     * @return array{group: string, component: string, position: string}
     */
    private static function addGroupHoldingAComponent(string $ownerIri, string $reference): array
    {
        $owner = self::findByIri($ownerIri);
        self::assertNotNull($owner);
        \assert(method_exists($owner, 'addComponentGroup'));
        $created = self::newComponentGroupWithComponents(1, $reference);
        $owner->addComponentGroup($created['group']);
        self::persistAndFlush($created['group'], ...$created['components'], ...$created['positions']);

        return [
            'group' => self::iriOf($created['group']),
            'component' => self::iriOf($created['components'][0]),
            'position' => self::iriOf($created['positions'][0]),
        ];
    }

    /**
     * @return array{published: string, draft: string}
     */
    private static function createRoutedPageWhoseGroupHoldsAPublishedComponentWithADraft(string $path): array
    {
        $page = self::newPage('mercure-page');
        $page->uiComponent = 'TestComponent';
        $page->layout = self::newLayout('mercure-layout');
        $group = self::newComponentGroup('mercure-group');
        $otherGroup = self::newComponentGroup('mercure-other-group');
        $page->addComponentGroup($group);
        $page->addComponentGroup($otherGroup);
        $published = new DummyPublishableComponent();
        $published->reference = 'published';
        $published->setPublishedAt(new \DateTime('-1 day'));
        $position = self::newComponentPosition($group, $published, 0);
        $route = self::newRoute($path)->setPage($page);
        $draft = new DummyPublishableComponent();
        $draft->reference = 'draft';
        $draft->setPublishedResource($published);
        self::persistAndFlush($page->layout, $page, $group, $otherGroup, $published, $position, $route, $draft);

        return ['published' => self::iriOf($published), 'draft' => self::iriOf($draft)];
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
