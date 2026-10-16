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
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublishCustomGroup;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithPublishedConstraints;

class PublishableAttributeReaderTest extends TestCase
{
    public function test_the_published_validation_groups_are_the_short_name_published_group_by_default(): void
    {
        $reader = new PublishableAttributeReader($this->createStub(ManagerRegistry::class));

        self::assertSame(['DummyUploadableAndPublishable:published'], $reader->getPublishedValidationGroups(DummyUploadableAndPublishable::class));
        self::assertSame(['DummyUploadableAndPublishable:published'], $reader->getPublishedValidationGroups(new DummyUploadableAndPublishable()));
    }

    public function test_a_classes_own_validation_groups_replace_the_published_group(): void
    {
        $reader = new PublishableAttributeReader($this->createStub(ManagerRegistry::class));

        self::assertSame(['custom_publish_group'], $reader->getPublishedValidationGroups(DummyUploadableRequiredOnPublishCustomGroup::class));
        self::assertSame(['custom_publish_group'], $reader->getPublishedValidationGroups(new DummyUploadableRequiredOnPublishCustomGroup()));
        self::assertSame(['publish_files', 'publish_images'], $reader->getPublishedValidationGroups(DummyUploadableWithPublishedConstraints::class));
    }
}
