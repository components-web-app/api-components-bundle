<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Api\CacheHeadersEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\ComponentPositionNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadata;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ComponentPositionNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function requests(): iterable
    {
        yield 'a sortValue' => [['sortValue' => 3, 'after' => '/a'], true];
        yield 'a null sortValue' => [['sortValue' => null], true];
        yield 'no sortValue' => [['after' => '/a'], false];
        yield 'an IRI' => ['/_/component_positions/1', false];
    }

    #[DataProvider('requests')]
    public function test_a_sort_value_in_the_request_is_recorded_on_the_position(mixed $data, bool $sent): void
    {
        $position = new ComponentPosition();
        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $denormalizer->expects(self::once())->method('denormalize')->willReturnCallback(static function (mixed $given, string $type, ?string $format, array $context) use ($data, $position): ComponentPosition {
            self::assertSame($data, $given);
            self::assertTrue($context['COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED']);

            return $position;
        });
        $normalizer = new ComponentPositionNormalizer(
            $this->createStub(PageDataProvider::class),
            new RequestStack(),
            $this->createStub(PublishableStatusChecker::class),
            $this->createStub(ManagerRegistry::class),
            $this->createStub(IriConverterInterface::class),
            $this->createStub(ResourceMetadataProvider::class),
        );
        $normalizer->setDenormalizer($denormalizer);

        self::assertTrue($normalizer->supportsDenormalization($data, ComponentPosition::class));
        self::assertSame($position, $normalizer->denormalize($data, ComponentPosition::class, 'jsonld'));
        self::assertSame($sent, $position->wasSortValueSent());
    }

    public function test_normalizing_for_an_admin_names_the_draft_without_changing_the_position(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $position = new ComponentPosition();
        $position->component = $published;

        $registry = $this->createStub(ManagerRegistry::class);
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($registry));
        $statusChecker->method('isGranted')->willReturn(true);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $component): ?object => $component === $published ? $draft : null);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/component/draft');

        $normalizedComponent = null;
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function (ComponentPosition $object) use (&$normalizedComponent): array {
            $normalizedComponent = $object->component;

            return [];
        });
        $normalizer = new ComponentPositionNormalizer(
            $this->createStub(PageDataProvider::class),
            new RequestStack(),
            $statusChecker,
            $registry,
            $iriConverter,
            $metadataProvider = new ResourceMetadataProvider(),
        );
        $normalizer->setNormalizer($inner);

        $normalizer->normalize($position, 'jsonld');

        self::assertSame($draft, $normalizedComponent);
        self::assertSame($published, $position->component);
        self::assertFalse($metadataProvider->findResourceMetadata($position)->getIsDynamicPosition());
    }

    public function test_a_component_which_is_not_publishable_is_exposed(): void
    {
        $component = new DummyComponent();
        $result = $this->normalizeStatic($component, granted: false, active: false, publishedAt: null);

        self::assertSame('/component', $result['data']['component']);
        self::assertSame($component, $result['normalized']->component);
        self::assertSame('/component', $result['metadata']->getStaticComponent());
    }

    public function test_an_active_publishable_component_is_exposed_to_a_caller_who_is_not_granted(): void
    {
        $component = new DummyPublishableComponent();
        $result = $this->normalizeStatic($component, granted: false, active: true, publishedAt: new \DateTimeImmutable('-1 day'));

        self::assertSame('/component', $result['data']['component']);
        self::assertSame($component, $result['normalized']->component);
        self::assertSame('/component', $result['metadata']->getStaticComponent());
        self::assertNull($result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_an_unpublished_component_is_exposed_to_a_granted_caller(): void
    {
        $component = new DummyPublishableComponent();
        $result = $this->normalizeStatic($component, granted: true, active: false, publishedAt: null);

        self::assertSame('/component', $result['data']['component']);
        self::assertSame($component, $result['normalized']->component);
        self::assertSame('/component', $result['metadata']->getStaticComponent());
    }

    public function test_a_granted_caller_is_given_the_draft_of_a_published_component(): void
    {
        $draft = new DummyPublishableComponent();
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: true, active: true, publishedAt: new \DateTimeImmutable('-1 day'), draft: $draft);

        self::assertSame('/draft', $result['data']['component']);
        self::assertSame($draft, $result['normalized']->component);
        self::assertSame('/draft', $result['metadata']->getStaticComponent());
    }

    public function test_an_unpublished_component_is_not_exposed_to_a_caller_who_is_not_granted(): void
    {
        $component = new DummyPublishableComponent();
        $result = $this->normalizeStatic($component, granted: false, active: false, publishedAt: null);

        self::assertArrayNotHasKey('component', $result['data']);
        self::assertSame('/_/component_positions/1', $result['data']['@id']);
        self::assertSame($component, $result['position']->component);
        self::assertNull($result['metadata']->getStaticComponent());
        self::assertNull($result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_a_scheduled_component_hidden_from_the_caller_records_its_publication_on_the_main_request(): void
    {
        $publishedAt = new \DateTimeImmutable('+1 hour');
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: $publishedAt);

        self::assertArrayNotHasKey('component', $result['data']);
        self::assertSame($publishedAt, $result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_the_earliest_hidden_publication_is_kept(): void
    {
        $earlier = new \DateTimeImmutable('+1 hour');
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: new \DateTimeImmutable('+2 hours'), recorded: $earlier);

        self::assertSame($earlier, $result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_an_earlier_hidden_publication_replaces_a_later_one(): void
    {
        $earlier = new \DateTimeImmutable('+1 hour');
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: $earlier, recorded: new \DateTimeImmutable('+2 hours'));

        self::assertSame($earlier, $result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_a_recorded_value_which_is_not_a_date_is_replaced(): void
    {
        $publishedAt = new \DateTimeImmutable('+1 hour');
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: $publishedAt, recorded: 'tomorrow');

        self::assertSame($publishedAt, $result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_a_hidden_component_is_recorded_on_the_main_request_not_a_sub_request(): void
    {
        $publishedAt = new \DateTimeImmutable('+1 hour');
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: $publishedAt, subRequest: true);

        self::assertSame($publishedAt, $result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_a_hidden_component_in_an_anonymous_read_for_mercure_is_not_recorded_on_the_writers_request(): void
    {
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: new \DateTimeImmutable('+1 hour'), subRequest: true, anonymousRead: true);

        self::assertArrayNotHasKey('component', $result['data']);
        self::assertNull($result['request']->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE));
    }

    public function test_a_hidden_component_with_no_request_is_still_hidden(): void
    {
        $result = $this->normalizeStatic(new DummyPublishableComponent(), granted: false, active: false, publishedAt: new \DateTimeImmutable('+1 hour'), withRequest: false);

        self::assertArrayNotHasKey('component', $result['data']);
    }

    /**
     * @return array{data: array<string, mixed>, normalized: ComponentPosition, position: ComponentPosition, metadata: ResourceMetadata, request: Request}
     */
    private function normalizeStatic(AbstractComponent $component, bool $granted, bool $active, ?\DateTimeInterface $publishedAt, ?AbstractComponent $draft = null, mixed $recorded = null, bool $withRequest = true, bool $subRequest = false, bool $anonymousRead = false): array
    {
        $position = new ComponentPosition();
        $position->component = $component;

        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $statusChecker->method('isGranted')->willReturn($granted);
        $statusChecker->method('isActivePublishedAt')->willReturn($active);
        $statusChecker->method('getPublicationDate')->willReturn($publishedAt);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn($draft);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (object $resource): string => $resource === $draft ? '/draft' : '/component');

        $request = new Request();
        if (null !== $recorded) {
            $request->attributes->set(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE, $recorded);
        }
        $requestStack = new RequestStack();
        if ($withRequest) {
            $requestStack->push($request);
        }
        if ($subRequest) {
            $requestStack->push(new Request(attributes: $anonymousRead ? [AnonymousResourceReader::REQUEST_ATTRIBUTE => true] : []));
        }

        $metadataProvider = new ResourceMetadataProvider();
        $normalizer = new ComponentPositionNormalizer(
            $this->createStub(PageDataProvider::class),
            $requestStack,
            $statusChecker,
            $registry,
            $iriConverter,
            $metadataProvider,
        );

        $normalized = null;
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function (ComponentPosition $object) use (&$normalized, $iriConverter): array {
            $normalized = clone $object;

            return ['@id' => '/_/component_positions/1', 'component' => $object->component ? $iriConverter->getIriFromResource($object->component) : null];
        });
        $normalizer->setNormalizer($inner);

        $data = $normalizer->normalize($position, 'jsonld');
        self::assertIsArray($data);
        self::assertInstanceOf(ComponentPosition::class, $normalized);

        return [
            'data' => $data,
            'normalized' => $normalized,
            'position' => $position,
            'metadata' => $metadataProvider->findResourceMetadata($position),
            'request' => $request,
        ];
    }
}
