<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Site;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\EntityStateReader;
use Silverback\ApiComponentsBundle\Fixture\Generator\Site\PropertyValue;
use Silverback\ApiComponentsBundle\Tests\Command\Fixture\ConstructorDefaultComponent;
use Silverback\ApiComponentsBundle\Tests\Fixture\Generator\GeneratorSiteTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyNavigationLink;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyTimestamped;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;

class EntityStateReaderTest extends TestCase
{
    use GeneratorSiteTrait;

    public function test_fields_left_at_their_default_and_identifiers_are_skipped(): void
    {
        $link = new DummyNavigationLink();
        $link->label = 'About';

        self::assertSame(['label' => 'About'], $this->values($this->reader()->read($link)));
    }

    public function test_a_field_is_compared_with_the_value_the_constructor_gives_it_not_its_declared_default(): void
    {
        $declared = new ConstructorDefaultComponent();
        $declared->tone = 'plain';

        self::assertSame(['tone' => 'plain'], $this->values($this->reader()->read($declared)));
        self::assertSame([], $this->values($this->reader()->read(new ConstructorDefaultComponent())));
    }

    public function test_a_public_property_is_written_directly_and_a_private_one_through_its_setter(): void
    {
        $link = new DummyNavigationLink();
        $link->theme = 'dark';
        $link->setInternalNote('check');

        $values = $this->byProperty($this->reader()->read($link));

        self::assertSame([PropertyValue::FIELD, 'dark', true, null], [$values['theme']->kind, $values['theme']->value, $values['theme']->writable, $values['theme']->setter]);
        self::assertSame([PropertyValue::FIELD, 'check', true, 'setInternalNote'], [$values['internalNote']->kind, $values['internalNote']->value, $values['internalNote']->writable, $values['internalNote']->setter]);
    }

    public function test_timestamps_are_skipped(): void
    {
        $timestamped = new DummyTimestamped();
        $timestamped->setCreatedAt(new \DateTimeImmutable('2020-01-01'));
        $timestamped->setModifiedAt(new \DateTime('2020-01-02'));

        self::assertSame([], $this->reader()->read($timestamped));
    }

    public function test_publishable_fields_are_read_apart_from_the_state(): void
    {
        $published = new DummyPublishableComponent();
        $published->reference = 'live';
        $published->setPublishedAt(new \DateTime('2020-01-01T00:00:00+00:00'));
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        (new \ReflectionProperty(DummyPublishableComponent::class, 'draftResource'))->setValue($published, $draft);
        $reader = $this->reader();

        self::assertSame(['reference' => 'live'], $this->values($reader->read($published)));
        self::assertSame([], $this->values($reader->read($draft)));
        self::assertEquals(new \DateTime('2020-01-01T00:00:00+00:00'), $reader->publishedAt($published));
        self::assertSame($draft, $reader->draftOf($published));
        self::assertNull($reader->publishedAt($draft));
        self::assertNull($reader->draftOf($draft));
        self::assertSame($published, $reader->publishedOf($draft));
        self::assertNull($reader->publishedOf($published));
    }

    public function test_an_object_that_is_not_publishable_has_no_publish_date_or_draft(): void
    {
        $reader = $this->reader();

        self::assertNull($reader->publishedAt(new DummyComponent()));
        self::assertNull($reader->draftOf(new DummyComponent()));
        self::assertNull($reader->publishedOf(new DummyComponent()));
    }

    public function test_a_stored_file_is_read_as_its_adapter_and_path_instead_of_its_storage_field(): void
    {
        $component = new DummyUploadableAndPublishable();
        $component->setFilename('components/photo-0a1b2c3d.jpg');

        $values = $this->byProperty($this->reader()->read($component));

        self::assertSame(['file'], array_keys($values));
        self::assertSame(PropertyValue::FILE, $values['file']->kind);
        self::assertSame(['adapter' => 'local', 'path' => 'components/photo-0a1b2c3d.jpg'], $values['file']->value);
    }

    public function test_associations_are_read_by_kind_and_skipped_ones_are_left_out(): void
    {
        $route = new Route();
        $related = new DummyComponent();
        $component = new DummyComponentWithRelated();
        $component->related = $related;
        $link = new DummyNavigationLink();
        $link->route = $route;
        $pageData = new PageDataWithComponent();
        $pageData->page = new Page();
        $pageData->component = new DummyComponent();
        $reader = $this->reader();

        $relatedValue = $this->byProperty($reader->read($component))['related'];
        $routeValue = $this->byProperty($reader->read($link))['route'];
        $pageDataValues = $this->byProperty($reader->read($pageData));

        self::assertSame([PropertyValue::COMPONENT, $related], [$relatedValue->kind, $relatedValue->value]);
        self::assertSame([PropertyValue::ROUTE, $route], [$routeValue->kind, $routeValue->value]);
        self::assertSame(PropertyValue::ENTITY, $pageDataValues['page']->kind);
        self::assertArrayNotHasKey('page', $this->byProperty($reader->read($pageData, ['page'])));
    }

    public function test_the_entity_is_initialised_before_it_is_read(): void
    {
        $link = new DummyNavigationLink();
        $metadataSource = $this->registry()->getManagerForClass(DummyNavigationLink::class);
        self::assertInstanceOf(EntityManagerInterface::class, $metadataSource);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturnCallback($metadataSource->getClassMetadata(...));
        $manager->expects($this->once())->method('initializeObject')->with($link);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $this->reader($registry)->read($link);
    }

    public function test_the_class_name_comes_from_the_mapping(): void
    {
        self::assertSame(DummyNavigationLink::class, $this->reader()->className(new DummyNavigationLink()));
    }

    public function test_an_unmapped_object_cannot_be_read(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('stdClass is not mapped by the Doctrine ORM.');

        $this->reader($registry)->className(new \stdClass());
    }

    private function reader(?ManagerRegistry $registry = null): EntityStateReader
    {
        $registry ??= $this->registry();

        return new EntityStateReader($registry, new UploadableAttributeReader($registry, true), new PublishableAttributeReader($registry), new TimestampedAttributeReader($registry));
    }

    /**
     * @param list<PropertyValue> $values
     *
     * @return array<string, mixed>
     */
    private function values(array $values): array
    {
        return array_map(static fn (PropertyValue $value): mixed => $value->value, $this->byProperty($values));
    }

    /**
     * @param list<PropertyValue> $values
     *
     * @return array<string, PropertyValue>
     */
    private function byProperty(array $values): array
    {
        $byProperty = [];
        foreach ($values as $value) {
            $byProperty[$value->property] = $value;
        }

        return $byProperty;
    }
}
