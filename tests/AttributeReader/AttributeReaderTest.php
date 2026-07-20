<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\AttributeReader;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;

/**
 * Exercises the shared traversal logic in the abstract AttributeReader through the concrete
 * PublishableAttributeReader (isConfigured resolves via reflection only).
 */
class AttributeReaderTest extends TestCase
{
    private function buildReader(): PublishableAttributeReader
    {
        return new PublishableAttributeReader($this->createStub(ManagerRegistry::class));
    }

    public function test_attribute_declared_directly_on_class_is_found(): void
    {
        self::assertTrue($this->buildReader()->isConfigured(DirectlyPublishableStub::class));
    }

    public function test_attribute_declared_on_grandparent_is_found(): void
    {
        // Kills While_ (line 121) and LogicalNot (line 123): the parent-class walk must climb past the
        // intermediate class (no attribute) up to the grandparent that carries it. A broken loop or an
        // un-negated condition stops after the first parent and reports "not configured".
        self::assertTrue($this->buildReader()->isConfigured(GrandchildOfPublishableStub::class));
    }

    public function test_attribute_declared_on_trait_is_found(): void
    {
        // Kills Foreach_ (line 139): the trait walk must iterate the class's traits to find the one
        // carrying the attribute.
        self::assertTrue($this->buildReader()->isConfigured(UsesPublishableTraitStub::class));
    }

    public function test_class_without_attribute_anywhere_is_not_configured(): void
    {
        self::assertFalse($this->buildReader()->isConfigured(NoAttributeAnywhereStub::class));
    }
}

#[Publishable]
class DirectlyPublishableStub
{
}

#[Publishable]
class PublishableAncestorStub
{
}

class IntermediateNoAttributeStub extends PublishableAncestorStub
{
}

class GrandchildOfPublishableStub extends IntermediateNoAttributeStub
{
}

#[Publishable]
trait PublishableMarkerTrait
{
}

class UsesPublishableTraitStub
{
    use PublishableMarkerTrait;
}

class NoAttributeAnywhereStub
{
}
