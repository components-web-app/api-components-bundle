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
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\HttpCache\CwaTagCollector;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Silverback\ApiComponentsBundle\Validator\PublishableValidator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Publishable]
class _PublishNowStub
{
    public ?\DateTimeInterface $publishedAt = null;

    public ?self $publishedResource = null;

    public ?self $draftResource = null;
}

#[Publishable]
class _PublishableComponentStub
{
    public string $id = 'component-id';

    public ?\DateTimeInterface $publishedAt = null;

    public ?self $publishedResource = null;

    public ?self $draftResource = null;

    public function getComponentPositions(): array
    {
        return [];
    }
}

class PublishableNormalizerTest extends TestCase
{
    public function test_a_manifest_neither_counts_a_components_locations_nor_validates_it_for_publishing(): void
    {
        $component = new _PublishableComponentStub();
        $component->draftResource = $draft = new _PublishableComponentStub();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::never())->method('count');
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $metadataProvider = new ResourceMetadataProvider();

        $context = $this->normalizedContext($component, [CwaTagCollector::MANIFEST_CONTEXT_KEY => true], $repository, $validator, $metadataProvider);

        self::assertSame($draft, $context['PUBLISHABLE_ASSOCIATION']);
        self::assertTrue($context[CwaTagCollector::MANIFEST_CONTEXT_KEY]);
        self::assertNull($metadataProvider->findResourceMetadata($component)->getPublishable()?->locationCount);
    }

    public function test_a_component_outside_a_manifest_has_its_locations_counted_and_is_validated_for_publishing(): void
    {
        $component = new _PublishableComponentStub();
        $component->publishedResource = $published = new _PublishableComponentStub();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('count')->with(['component' => $component])->willReturn(3);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())->method('validate')->with($component, [PublishableValidator::PUBLISHED_KEY => true]);
        $metadataProvider = new ResourceMetadataProvider();

        $context = $this->normalizedContext($component, [CwaTagCollector::MANIFEST_CONTEXT_KEY => false], $repository, $validator, $metadataProvider);

        self::assertSame($published, $context['PUBLISHABLE_ASSOCIATION']);
        self::assertSame(3, $metadataProvider->findResourceMetadata($component)->getPublishable()?->locationCount);
    }

    private function normalizedContext(_PublishableComponentStub $component, array $context, EntityRepository $repository, ValidatorInterface $validator, ResourceMetadataProvider $metadataProvider): array
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $statusChecker->method('isGranted')->willReturn(true);
        $statusChecker->method('isActivePublishedAt')->willReturn(true);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $object, string $field): mixed => $object->{$field});
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getClassMetadata')->willReturn($classMetadata);
        $manager->method('getRepository')->willReturn($repository);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $pageDataMetadataProvider = $this->createStub(PageDataMetadataProvider::class);
        $pageDataMetadataProvider->method('createAll')->willReturn([]);

        $received = null;
        $inner = $this->createStub(NormalizerInterface::class);
        $inner->method('normalize')->willReturnCallback(static function (mixed $object, ?string $format, array $context) use (&$received): array {
            $received = $context;

            return [];
        });

        $normalizer = new PublishableNormalizer(
            $statusChecker,
            $registry,
            new RequestStack(),
            $validator,
            $this->createStub(IriConverterInterface::class),
            $this->createStub(UploadableFileManager::class),
            $metadataProvider,
            $this->createStub(EventDispatcherInterface::class),
            $pageDataMetadataProvider,
        );
        $normalizer->setNormalizer($inner);
        $normalizer->normalize($component, 'jsonld', $context);

        return $received;
    }

    public function test_a_publication_date_of_now_reaches_the_denormalizer_as_the_servers_current_time(): void
    {
        $before = new \DateTimeImmutable();
        $data = $this->denormalizedData(['publishedAt' => 'now', 'reference' => 'r']);
        $after = new \DateTimeImmutable();

        self::assertSame('r', $data['reference']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $data['publishedAt']);
        $publishedAt = new \DateTimeImmutable($data['publishedAt']);
        self::assertGreaterThanOrEqual($before->format('Uv'), $publishedAt->format('Uv'));
        self::assertLessThanOrEqual($after->format('Uv'), $publishedAt->format('Uv'));
    }

    public function test_only_the_exact_string_now_is_resolved(): void
    {
        foreach (['Now', ' now', 'now ', 'tomorrow', '2999-12-31T23:59:59+00:00', null] as $value) {
            self::assertSame(['publishedAt' => $value], $this->denormalizedData(['publishedAt' => $value]), var_export($value, true));
        }
        self::assertSame(['reference' => 'r'], $this->denormalizedData(['reference' => 'r']));
    }

    public function test_the_associations_to_the_draft_and_published_resource_are_never_written_by_the_client(): void
    {
        self::assertSame(['reference' => 'r'], $this->denormalizedData(['publishedResource' => '/x/1', 'draftResource' => '/x/2', 'reference' => 'r']));
    }

    public function test_a_caller_without_the_publication_permission_creates_a_resource_published_now(): void
    {
        $before = date('Y-m-d H:i:s');
        $data = $this->denormalizedData(['publishedAt' => '2999-01-01T00:00:00+00:00', 'reference' => 'r'], granted: false);

        self::assertSame('r', $data['reference']);
        self::assertGreaterThanOrEqual($before, $data['publishedAt']);
        self::assertLessThanOrEqual(date('Y-m-d H:i:s'), $data['publishedAt']);
    }

    public function test_a_caller_with_the_publication_permission_creates_a_resource_with_the_publication_date_it_sends(): void
    {
        self::assertSame(['publishedAt' => '2999-01-01T00:00:00+00:00'], $this->denormalizedData(['publishedAt' => '2999-01-01T00:00:00+00:00']));
    }

    public function test_a_request_for_the_published_resource_is_denormalized_as_sent_without_a_publication_date_for_a_caller_without_the_permission(): void
    {
        self::assertSame(['reference' => 'r'], $this->denormalizedData(['publishedAt' => '2999-01-01T00:00:00+00:00', 'reference' => 'r'], granted: false, requestForPublished: true));
    }

    public function test_a_request_for_the_published_resource_never_creates_a_draft(): void
    {
        $object = new _PublishNowStub();
        $object->publishedAt = new \DateTimeImmutable('-1 day');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $context = null;

        $this->denormalizedData(['reference' => 'r'], requestForPublished: true, objectToPopulate: $object, registry: $registry, receivedContext: $context);

        self::assertSame($object, $context[AbstractNormalizer::OBJECT_TO_POPULATE]);
    }

    private function denormalizedData(array $data, bool $granted = true, bool $requestForPublished = false, ?object $objectToPopulate = null, ?ManagerRegistry $registry = null, ?array &$receivedContext = null): array
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $statusChecker->method('isGranted')->willReturn($granted);
        $statusChecker->method('isRequestForPublished')->willReturn($requestForPublished);
        $statusChecker->method('isActivePublishedAt')->willReturn(true);

        $received = null;
        $inner = $this->createStub(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturnCallback(static function (mixed $data, string $type, ?string $format, array $context) use (&$received, &$receivedContext): \stdClass {
            $received = $data;
            $receivedContext = $context;

            return new \stdClass();
        });

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $normalizer = $this->createNormalizer($registry ?? $this->createStub(ManagerRegistry::class), $statusChecker, $requestStack);
        $normalizer->setDenormalizer($inner);
        $normalizer->denormalize($data, _PublishNowStub::class, null, null === $objectToPopulate ? [] : [AbstractNormalizer::OBJECT_TO_POPULATE => $objectToPopulate]);

        return $received;
    }

    public function test_creating_a_draft_fails_clearly_when_the_class_is_not_managed_by_the_orm(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('persist');
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for class %s', \stdClass::class));
        $this->createNormalizer($registry)->createDraft(new \stdClass(), new Publishable(), \stdClass::class);
    }

    public function test_creating_a_draft_fails_clearly_when_the_class_has_no_manager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for class %s', \stdClass::class));
        $this->createNormalizer($registry)->createDraft(new \stdClass(), new Publishable(), \stdClass::class);
    }

    private function createNormalizer(ManagerRegistry $registry, ?PublishableStatusChecker $statusChecker = null, RequestStack $requestStack = new RequestStack()): PublishableNormalizer
    {
        return new PublishableNormalizer(
            $statusChecker ?? $this->createStub(PublishableStatusChecker::class),
            $registry,
            $requestStack,
            $this->createStub(ValidatorInterface::class),
            $this->createStub(IriConverterInterface::class),
            $this->createStub(UploadableFileManager::class),
            $this->createStub(ResourceMetadataProvider::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(PageDataMetadataProvider::class),
        );
    }
}
