<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\Realtime\LastWriteResolver;
use Silverback\ApiComponentsBundle\Helper\Realtime\RealtimeUserResolver;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PersistedNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadata;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataInterface;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AllowMockObjectsWithoutExpectations]
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

    public function test_a_write_carrying_a_client_id_records_the_last_write_in_the_metadata(): void
    {
        $resourceMetadata = new ResourceMetadata();
        $resourceMetadata->setLastWrite(['clientId' => 'stale', 'userId' => 'stale']);

        $this->lastWriteNormalizer($resourceMetadata, Request::create('/component/dummy_components/1', 'PATCH'))->normalize(new DummyComponent(), 'jsonld');

        self::assertSame(['clientId' => '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b', 'userId' => '/users/1', 'at' => '2026-10-21T10:00:00.250+00:00'], $resourceMetadata->getLastWrite());
    }

    public function test_a_read_clears_a_last_write_left_in_the_metadata(): void
    {
        $resourceMetadata = new ResourceMetadata();
        $resourceMetadata->setLastWrite(['clientId' => '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b', 'userId' => '/users/1', 'at' => '2026-10-21T10:00:00.250+00:00']);

        $this->lastWriteNormalizer($resourceMetadata, Request::create('/component/dummy_components/1', 'GET'))->normalize(new DummyComponent(), 'jsonld');

        self::assertNull($resourceMetadata->getLastWrite());
    }

    public function test_no_last_write_is_recorded_without_a_resolver(): void
    {
        $resourceMetadata = new ResourceMetadata();
        $this->resourceMetadataMock->method('findResourceMetadata')->willReturn($resourceMetadata);

        $this->apiNormalizer->normalize(new DummyComponent(), 'jsonld');

        self::assertNull($resourceMetadata->getLastWrite());
    }

    private function lastWriteNormalizer(ResourceMetadata $resourceMetadata, Request $request): PersistedNormalizer
    {
        $request->headers->set(LastWriteResolver::HEADER, '0b9f7a3e-2c4d-4e5f-8a6b-7c8d9e0f1a2b');
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken((new User())->setUsername('editor'), 'main', ['ROLE_ADMIN']));
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/users/1');
        $this->resourceMetadataMock->method('findResourceMetadata')->willReturn($resourceMetadata);

        $normalizer = new PersistedNormalizer($this->entityManagerMock, $this->resourceClassResolverMock, $this->resourceMetadataMock, new LastWriteResolver($requestStack, new RealtimeUserResolver($tokenStorage, $iriConverter), new MockClock('2026-10-21T10:00:00.250+00:00')));
        $normalizer->setNormalizer($this->normalizerMock);

        return $normalizer;
    }
}
