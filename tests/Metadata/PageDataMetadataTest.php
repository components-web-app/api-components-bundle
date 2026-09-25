<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;

class PageDataMetadataTest extends TestCase
{
    public function test_a_property_typed_as_the_component_class_matches(): void
    {
        self::assertSame(['component', 'anyComponent'], $this->propertiesFor(DummyComponent::class));
    }

    public function test_a_property_typed_as_a_parent_class_of_the_component_matches(): void
    {
        self::assertSame(['anyComponent'], $this->propertiesFor(DummyPublishableComponent::class));
    }

    public function test_a_property_typed_as_a_subclass_of_the_component_class_does_not_match(): void
    {
        self::assertSame(['anyComponent'], $this->propertiesFor(AbstractComponent::class));
    }

    public function test_a_class_that_is_not_a_component_matches_nothing(): void
    {
        self::assertSame([], $this->propertiesFor(\stdClass::class));
    }

    /**
     * @return list<string>
     */
    private function propertiesFor(string $componentClass): array
    {
        $metadata = new PageDataMetadata(PageDataWithComponent::class);
        $metadata->addProperty(new PageDataPropertyMetadata('component', DummyComponent::class, 'DummyComponent'));
        $metadata->addProperty(new PageDataPropertyMetadata('anyComponent', AbstractComponent::class, 'AbstractComponent'));

        return array_values($metadata->findPropertiesByComponentClass($componentClass)->map(static fn (PageDataPropertyMetadata $property) => $property->getProperty())->toArray());
    }
}
