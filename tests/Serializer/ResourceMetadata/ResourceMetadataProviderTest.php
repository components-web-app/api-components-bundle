<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\ResourceMetadata;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Service\ResetInterface;

class ResourceMetadataProviderTest extends TestCase
{
    public function test_a_resource_has_one_metadata_until_reset(): void
    {
        $provider = new ResourceMetadataProvider();
        $resource = new \stdClass();

        self::assertFalse($provider->resourceMetadataExists($resource));
        $metadata = $provider->findResourceMetadata($resource);
        self::assertTrue($provider->resourceMetadataExists($resource));
        self::assertSame($metadata, $provider->findResourceMetadata($resource));
        self::assertNotSame($metadata, $provider->findResourceMetadata(new \stdClass()));

        self::assertInstanceOf(ResetInterface::class, $provider);
        $provider->reset();

        self::assertFalse($provider->resourceMetadataExists($resource));
        self::assertNotSame($metadata, $provider->findResourceMetadata($resource));
    }

    public function test_the_provider_keeps_neither_a_freed_resource_nor_its_metadata(): void
    {
        $provider = new ResourceMetadataProvider();
        $resource = new \stdClass();
        $resourceReference = \WeakReference::create($resource);
        $metadataReference = \WeakReference::create($provider->findResourceMetadata($resource));

        unset($resource);
        gc_collect_cycles();

        self::assertNull($resourceReference->get());
        self::assertNull($metadataReference->get());
    }

    public function test_metadata_whose_violations_reference_the_resource_is_freed_by_a_reset(): void
    {
        $provider = new ResourceMetadataProvider();
        $resource = new \stdClass();
        $provider->findResourceMetadata($resource)->setViolations(new ConstraintViolationList([new ConstraintViolation('invalid', null, [], $resource, 'title', null)]));
        $resourceReference = \WeakReference::create($resource);

        unset($resource);
        $provider->reset();
        gc_collect_cycles();

        self::assertNull($resourceReference->get());
    }
}
