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
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class ComponentTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use ComponentSiteTrait;

    private const string GROUP_ID = '41c57f7a-9f69-11ea-8188-acde48001122';

    public function test_i_can_get_a_components_usage(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createDummyComponentInPageDataAndPosition();

        self::requestJsonLd($client, 'GET', $site['component'] . '/usage');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('positionCount', 1);
        self::assertJsonNodeSame('pageDataCount', 1);
        self::assertJsonNodeSame('total', 2);
    }

    public function test_i_can_create_a_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'POST', '/component/dummy_components', '{"uiComponent": "AnotherComponent", "uiClassNames": ["my-class"]}');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component.schema.json');
        self::assertJsonNodeSame('@context', '/contexts/DummyComponent');
        self::assertJsonNodeSame('uiComponent', 'AnotherComponent');
        self::assertJsonNodeSame('uiClassNames[0]', 'my-class');
    }

    public function test_i_can_create_a_component_and_the_component_position_at_the_same_time(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $positions = self::createGroupWithTheId(3, self::GROUP_ID);

        self::requestJsonLd($client, 'POST', '/component/dummy_components', [
            'componentPositions' => [['componentGroup' => '/_/component_groups/' . self::GROUP_ID, 'after' => $positions[0]]],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('component.schema.json');
        $component = self::jsonNode('@id');
        $position = self::jsonNode('componentPositions[0]');
        self::requestJsonLd($client, 'GET', $position);
        self::assertJsonMatchesSchemaFile('component_position.schema.json');
        self::assertJsonNodeSame('componentGroup', '/_/component_groups/' . self::GROUP_ID);
        self::assertJsonNodeSame('component', $component);
        self::assertJsonNodeSame('sortValue', 1);
    }

    public function test_a_component_update_can_add_a_component_position(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(0);
        $component = self::createDummyComponent();

        self::requestJsonLd($client, 'PATCH', $component, ['componentPositions' => [['componentGroup' => $group['group']]]]);

        self::assertResponseStatusCodeSame(200);
        $position = self::jsonNode('componentPositions[0]');
        self::requestJsonLd($client, 'GET', $position);
        self::assertJsonNodeSame('componentGroup', $group['group']);
        self::assertJsonNodeSame('component', $component);
    }

    public function test_i_can_delete_a_component(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = new DummyComponent();
        self::persistAndFlush($component);

        self::requestJsonLd($client, 'DELETE', self::iriOf($component));

        self::assertResponseStatusCodeSame(204);
    }

    #[DataProvider('resourcesHoldingTheComponent')]
    public function test_a_component_still_in_use_is_not_deleted(string $resource): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createDummyComponentInPageDataAndPosition();

        self::requestJsonLd($client, 'DELETE', $site[$resource]);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists($site['component']);
    }

    public static function resourcesHoldingTheComponent(): iterable
    {
        yield 'page_data' => ['pageData'];
        yield 'component_position' => ['position'];
    }

    public function test_when_deleting_a_component_the_component_position_should_not_be_deleted_if_it_has_a_dynamic_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);
        self::updateResource($group['positions'][0], static function (object $position): void {
            \assert($position instanceof ComponentPosition);
            $position->setPageDataProperty('ref');
            $position->pageDataClass = PageDataWithComponent::class;
        });

        self::requestJsonLd($client, 'DELETE', $group['components'][0]);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceExists($group['positions'][0]);
    }

    public function test_when_deleting_a_component_the_component_position_should_be_deleted_if_it_does_not_have_a_dynamic_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);

        self::requestJsonLd($client, 'DELETE', $group['components'][0]);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($group['positions'][0]);
    }

    public function test_an_orphaned_component_from_page_data_is_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataWithRoute('/');
        $component = self::iriOf($site['component']);

        self::requestJsonLd($client, 'DELETE', self::iriOf($site['pageData']));

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($component);
    }

    public function test_an_orphaned_component_from_a_component_position_is_deleted(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $group = self::createGroupWithComponentIris(1);

        self::requestJsonLd($client, 'DELETE', $group['positions'][0]);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($group['components'][0]);
    }

    /**
     * @return list<string>
     */
    private static function createGroupWithTheId(int $count, string $id): array
    {
        $manager = static::getEntityManager();
        $group = self::newComponentGroup('collection');
        static::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($group, true);
        $manager->persist($group);
        (new \ReflectionProperty(ComponentGroup::class, 'id'))->setValue($group, Uuid::fromString($id));
        $manager->flush();
        $manager->clear();
        $group = $manager->find(ComponentGroup::class, $id);
        self::assertInstanceOf(ComponentGroup::class, $group);
        $timestamped = [];
        $positions = [];
        for ($sortValue = 0; $sortValue < $count; ++$sortValue) {
            $component = new DummyComponent();
            $positions[] = $position = self::newComponentPosition($group, $component, $sortValue);
            array_push($timestamped, $component, $position);
        }
        self::persistAndFlush(...$timestamped);

        return array_map(static fn (ComponentPosition $position): string => self::iriOf($position), $positions);
    }
}
