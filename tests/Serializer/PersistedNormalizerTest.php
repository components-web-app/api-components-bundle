<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Serializer;

use ApiPlatform\Api\ResourceClassResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PersistedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadata;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataInterface;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PersistedNormalizerTest extends TestCase
{
    private PersistedNormalizer $apiNormalizer;
    /**
     * @var ResourceClassResolverInterface|MockObject
     */
    private $resourceClassResolverMock;
    /**
     * @var EntityManagerInterface|MockObject
     */
    private $entityManagerMock;
    /**
     * @var ResourceMetadataInterface|MockObject
     */
    private $resourceMetadataMock;
    /**
     * @var MockObject|NormalizerInterface
     */
    private $normalizerMock;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->resourceClassResolverMock = $this->createMock(ResourceClassResolverInterface::class);
        $this->resourceMetadataMock = $this->createMock(ResourceMetadataProvider::class);
        $this->normalizerMock = $this->createMock(NormalizerInterface::class);
        $this->apiNormalizer = new PersistedNormalizer($this->entityManagerMock, $this->resourceClassResolverMock, $this->resourceMetadataMock);
        $this->apiNormalizer->setNormalizer($this->normalizerMock);
    }

    //    public function test_normalizer_is_called(): void
    //    {
    //
    //    }

    public function tests_does_not_support_normalization_never_reaching_resource_class_resolver(): void
    {
        $this->resourceClassResolverMock
            ->expects(self::never())
            ->method('isResourceClass');

        $format = 'jsonld';
        self::assertFalse($this->apiNormalizer->supportsNormalization(new DummyComponent(), $format, ['PERSISTED_NORMALIZER_ALREADY_CALLED' => [null]]));
        self::assertFalse($this->apiNormalizer->supportsNormalization([], $format, []));
        self::assertFalse($this->apiNormalizer->supportsNormalization('string', $format, []));
        $traversable = $this->createMock(\Traversable::class);
        self::assertFalse($this->apiNormalizer->supportsNormalization($traversable, $format, []));
    }

    public function test_does_not_support_non_api_platform_resource_normalization(): void
    {
        $dummyComponent = new DummyComponent();
        $format = 'jsonld';

        $this->resourceClassResolverMock
            ->expects(self::once())
            ->method('isResourceClass')
            ->with(DummyComponent::class)
            ->willReturn(false);
        self::assertFalse($this->apiNormalizer->supportsNormalization($dummyComponent, $format, []));
    }

    public function tests_supports_normalization(): void
    {
        $dummyComponent = new DummyComponent();
        $format = 'jsonld';

        $this->resourceClassResolverMock
            ->expects(self::once())
            ->method('isResourceClass')
            ->with(DummyComponent::class)
            ->willReturn(true);
        self::assertTrue($this->apiNormalizer->supportsNormalization($dummyComponent, $format, []));
    }

    public function test_normalization_result_entity_is_persisted(): void
    {
        $dummyComponent = new DummyComponent();
        $format = 'jsonld';

        $this->normalizerMock
            ->expects(self::once())
            ->method('normalize')
            ->with(
                $dummyComponent,
                $format,
                [
                    'PERSISTED_NORMALIZER_ALREADY_CALLED' => [null],
                    'default_context_param' => 'default_value',
                    'silverback_api_components_bundle_metadata' => ['persisted' => true],
                ]
            )
            ->willReturn('anything');

        $this->entityManagerMock
            ->expects(self::once())
            ->method('contains')
            ->with($dummyComponent)
            ->willReturn(true);

        $resourceMetadata = new ResourceMetadata();
        $this->resourceMetadataMock
            ->expects(self::once())
            ->method('findResourceMetadata')
            ->with($dummyComponent)
            ->willReturn($resourceMetadata);

        $result = $this->apiNormalizer->normalize($dummyComponent, $format, ['default_context_param' => 'default_value', 'silverback_api_components_bundle_metadata' => ['persisted' => true]]);
        self::assertTrue($resourceMetadata->getPersisted());
        self::assertEquals('anything', $result);
    }

    public function test_normalization_result_entity_is_not_persisted(): void
    {
        $dummyComponent = new DummyComponent();
        $format = 'jsonld';

        $this->normalizerMock
            ->expects(self::once())
            ->method('normalize')
            ->with(
                $dummyComponent,
                $format,
                [
                    'PERSISTED_NORMALIZER_ALREADY_CALLED' => [null],
                    'silverback_api_components_bundle_metadata' => ['persisted' => false],
                ]
            )
            ->willReturn('anything');

        $this->entityManagerMock
            ->expects(self::once())
            ->method('contains')
            ->with($dummyComponent)
            ->willReturn(false);

        $result = $this->apiNormalizer->normalize($dummyComponent, $format, ['silverback_api_components_bundle_metadata' => ['persisted' => false]]);
        self::assertEquals('anything', $result);
    }
}
