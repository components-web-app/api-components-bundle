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
use Silverback\ApiComponentsBundle\Annotation\RoutePrefix;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\InvalidRoutePrefixException;

class RoutePrefixAttributeReaderTest extends TestCase
{
    private function reader(): RoutePrefixAttributeReader
    {
        return new RoutePrefixAttributeReader($this->createStub(ManagerRegistry::class));
    }

    public function test_the_prefix_of_a_configured_class_is_read(): void
    {
        self::assertSame('/announcements', $this->reader()->getConfiguration(RoutePrefixedStub::class)->prefix);
    }

    public function test_the_prefix_of_an_object_is_read(): void
    {
        self::assertSame('/announcements', $this->reader()->getConfiguration(new RoutePrefixedStub())->prefix);
    }

    public function test_a_subclass_inherits_its_parents_prefix(): void
    {
        self::assertSame('/announcements', $this->reader()->getConfiguration(RoutePrefixedChildStub::class)->prefix);
    }

    public function test_a_class_without_the_attribute_is_not_configured(): void
    {
        self::assertFalse($this->reader()->isConfigured(NotRoutePrefixedStub::class));
        self::assertTrue($this->reader()->isConfigured(RoutePrefixedStub::class));
    }

    public function test_reading_a_class_without_the_attribute_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->reader()->getConfiguration(NotRoutePrefixedStub::class);
    }

    public function test_an_invalid_prefix_fails_loudly_rather_than_reading_as_no_prefix(): void
    {
        $this->expectException(InvalidRoutePrefixException::class);

        $this->reader()->isConfigured(InvalidRoutePrefixStub::class);
    }
}

#[RoutePrefix('/announcements')]
class RoutePrefixedStub
{
}

class RoutePrefixedChildStub extends RoutePrefixedStub
{
}

class NotRoutePrefixedStub
{
}

#[RoutePrefix('/')]
class InvalidRoutePrefixStub
{
}
