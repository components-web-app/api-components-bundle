<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class LayoutTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use SiteTrait;

    public function test_i_can_create_a_layout(): void
    {
        $client = self::adminClient();

        self::requestJsonLd($client, 'POST', '/_/layouts', <<<'JSON'
            {
              "reference": "primary",
              "uiComponent": "PrimaryLayout",
              "uiClassNames": ["has-border"]
            }
            JSON);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('layout.schema.json');
        self::assertJsonNodeExists('reference');
        self::assertJsonNodeExists('uiComponent');
        self::assertJsonNodeExists('uiClassNames');
    }

    #[DataProvider('invalidLayouts')]
    public function test_the_layout_resource_validates_properly(string $reference, ?string $uiComponent, string $propertyPath, string $message): void
    {
        $client = self::adminClient();
        self::createLayout('primary');

        self::requestJsonLd($client, 'POST', '/_/layouts', ['reference' => $reference, 'uiComponent' => $uiComponent]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertJsonNodeSame('violations[0].propertyPath', $propertyPath);
        self::assertJsonNodeSame('violations[0].message', $message);
    }

    /**
     * @return iterable<string, array{string, ?string, string, string}>
     */
    public static function invalidLayouts(): iterable
    {
        yield 'an empty reference' => ['', 'PrimaryLayout', 'reference', 'Please enter a reference.'];
        yield 'a reference already used' => ['primary', 'PrimaryLayout', 'reference', 'There is already a Layout with that reference.'];
        yield 'no uiComponent' => ['new', null, 'uiComponent', 'You must define the uiComponent for this resource.'];
    }

    public function test_i_can_delete_a_layout(): void
    {
        $client = self::adminClient();
        $layout = self::createLayout();

        self::requestJsonLd($client, 'DELETE', self::iriOf($layout));

        self::assertResponseStatusCodeSame(204);
    }

    public function test_the_layout_resources_can_be_listed(): void
    {
        $client = self::adminClient();
        self::createLayout('primary');
        self::createLayout('secondary');

        self::requestJsonLd($client, 'GET', '/_/layouts');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 2);
    }

    public function test_the_layout_search_parameter_matches_the_reference(): void
    {
        $client = self::adminClient();
        self::createLayout('primary');
        self::createLayout('secondary');

        self::requestJsonLd($client, 'GET', '/_/layouts?search=primary');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
    }

    public function test_the_layout_resources_can_be_ordered_ascending_by_reference(): void
    {
        self::assertLayoutsOrdered('/_/layouts?order[reference]=asc', ['1', '2'], ['1', '2']);
    }

    public function test_the_layout_resources_can_be_ordered_descending_by_reference(): void
    {
        self::assertLayoutsOrdered('/_/layouts?order[reference]=desc', ['1', '2'], ['2', '1']);
    }

    public function test_the_layout_resources_can_be_ordered_ascending_by_created_at(): void
    {
        self::assertLayoutsOrdered('/_/layouts?order[createdAt]=asc', ['layout_1' => 'now', 'layout_2' => '+10 seconds'], ['layout_1', 'layout_2']);
    }

    public function test_the_layout_resources_can_be_ordered_descending_by_created_at(): void
    {
        self::assertLayoutsOrdered('/_/layouts?order[createdAt]=desc', ['layout_1' => 'now', 'layout_2' => '+10 seconds'], ['layout_2', 'layout_1']);
    }

    public function test_the_layout_search_parameter_matches_the_ui_component(): void
    {
        $client = self::adminClient();
        self::createLayout('primary', 'PrimaryLayout');
        self::createLayout('secondary', 'SecondaryLayout');

        self::requestJsonLd($client, 'GET', '/_/layouts?search=PrimaryLayout');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', 1);
        self::assertJsonNodeSame('member[0].reference', 'primary');
    }

    public function test_layout_component_groups_are_returned_as_iri_strings_not_embedded_objects(): void
    {
        $client = self::adminClient();
        $page = self::newPage();
        $layout = self::newLayout();
        $created = self::newComponentGroupWithComponents(1);
        $manager = self::getEntityManager();
        $timestamped = static::getContainer()->get(TimestampedDataPersister::class);
        foreach ([$page, $layout, $created['group'], ...$created['positions']] as $entity) {
            $timestamped->persistTimestampedFields($entity, true);
        }
        foreach ([$page, $layout, $created['group'], ...$created['components'], ...$created['positions']] as $entity) {
            $manager->persist($entity);
            $manager->flush();
        }
        $page->addComponentGroup($created['group']);
        $layout->addComponentGroup($created['group']);
        $manager->flush();
        static::rebootKernel();

        self::requestJsonLd($client, 'GET', self::iriOf($layout));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('componentGroups[0]', self::iriOf($created['group']));
    }

    /**
     * @param array<int|string, string> $layouts
     * @param list<string>              $expectedReferences
     */
    private static function assertLayoutsOrdered(string $url, array $layouts, array $expectedReferences): void
    {
        $client = self::adminClient();
        foreach ($layouts as $reference => $createdAt) {
            if (\is_int($reference)) {
                self::createLayout($createdAt);
            } else {
                self::createLayoutCreatedAt($reference, $createdAt);
            }
        }

        self::requestJsonLd($client, 'GET', $url);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('member', \count($expectedReferences));
        foreach ($expectedReferences as $index => $reference) {
            self::assertJsonNodeSame(\sprintf('member[%d].reference', $index), $reference);
        }
    }

    private static function createLayoutCreatedAt(string $reference, string $createdAt): Layout
    {
        $layout = self::newLayout($reference);
        $layout->setCreatedAt(new \DateTimeImmutable($createdAt));
        static::getContainer()->get(TimestampedDataPersister::class)->persistTimestampedFields($layout, false);
        $manager = self::getEntityManager();
        $manager->persist($layout);
        $manager->flush();
        static::rebootKernel();

        return $layout;
    }

    private static function adminClient(): Client
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        return $client;
    }
}
