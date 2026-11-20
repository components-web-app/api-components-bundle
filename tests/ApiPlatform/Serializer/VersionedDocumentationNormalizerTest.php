<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Serializer;

use ApiPlatform\Documentation\Documentation;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Serializer\VersionedDocumentationNormalizer;
use Silverback\ApiComponentsBundle\AttributeReader\ExplicitAllowOnlyAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\AnnouncementPageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class VersionedDocumentationNormalizerTest extends TestCase
{
    private const array SHORT_NAMES = [
        AnnouncementPageData::class => 'AnnouncementPageData',
        PageData::class => 'PageData',
        RestrictedComponent::class => 'RestrictedComponent',
        DummyComponent::class => 'DummyComponent',
        RoutePrefixedNonPageDataStub::class => 'RoutePrefixedNonPageDataStub',
    ];

    public function test_a_supported_class_of_a_prefixed_page_data_class_carries_its_route_prefix(): void
    {
        $doc = $this->normalize(self::supportedClasses('supportedClass'));

        self::assertSame(['title' => 'AnnouncementPageData', 'routePrefix' => '/announcements'], $doc['supportedClass'][0]);
    }

    public function test_a_supported_class_without_a_route_prefix_or_explicit_allow_only_is_unchanged(): void
    {
        $doc = $this->normalize(self::supportedClasses('supportedClass'));

        self::assertSame(['title' => 'PageData'], $doc['supportedClass'][1]);
        self::assertSame(['title' => 'DummyComponent'], $doc['supportedClass'][3]);
    }

    public function test_an_explicit_allow_only_class_is_flagged(): void
    {
        $doc = $this->normalize(self::supportedClasses('supportedClass'));

        self::assertSame(['title' => 'RestrictedComponent', 'explicitAllowOnly' => true], $doc['supportedClass'][2]);
    }

    public function test_a_route_prefix_on_a_class_that_is_not_page_data_is_not_advertised(): void
    {
        $doc = $this->normalize(self::supportedClasses('supportedClass'));

        self::assertSame(['title' => 'RoutePrefixedNonPageDataStub'], $doc['supportedClass'][4]);
    }

    public function test_the_hydra_prefixed_supported_class_key_is_handled(): void
    {
        $doc = $this->normalize(self::supportedClasses('hydra:supportedClass'));

        self::assertSame(['title' => 'AnnouncementPageData', 'routePrefix' => '/announcements'], $doc['hydra:supportedClass'][0]);
        self::assertSame(['title' => 'RestrictedComponent', 'explicitAllowOnly' => true], $doc['hydra:supportedClass'][2]);
    }

    public function test_entries_that_are_not_arrays_or_have_no_title_are_left_alone(): void
    {
        $doc = $this->normalize(['supportedClass' => ['a string', ['id' => 'no-title']]]);

        self::assertSame(['a string', ['id' => 'no-title']], $doc['supportedClass']);
    }

    public function test_a_document_without_supported_classes_is_returned_as_it_is(): void
    {
        self::assertSame(['@id' => '/docs'], $this->normalize(['@id' => '/docs'], ''));
    }

    public function test_the_version_is_extended_with_the_bundle_version(): void
    {
        self::assertSame(['version' => OpenApiFactory::getExtendedVersion('1.2.3')], $this->normalize([], '1.2.3')['info']);
    }

    public function test_the_supported_classes_are_read_once(): void
    {
        $names = $this->createMock(ResourceNameCollectionFactoryInterface::class);
        $names->expects(self::once())->method('create')->willReturn(new ResourceNameCollection(array_keys(self::SHORT_NAMES)));
        $normalizer = $this->normalizer(self::supportedClasses('supportedClass'), $names);

        $normalizer->normalize(new Documentation(new ResourceNameCollection()));
        $doc = $normalizer->normalize(new Documentation(new ResourceNameCollection()));

        self::assertSame('/announcements', $doc['supportedClass'][0]['routePrefix']);
    }

    private function normalize(array $decoratedOutput, string $version = ''): array
    {
        return $this->normalizer($decoratedOutput)->normalize(new Documentation(new ResourceNameCollection(), '', '', $version));
    }

    private function normalizer(array $decoratedOutput, ?ResourceNameCollectionFactoryInterface $names = null): VersionedDocumentationNormalizer
    {
        $decorated = $this->createStub(NormalizerInterface::class);
        $decorated->method('normalize')->willReturn($decoratedOutput);
        if (null === $names) {
            $names = $this->createStub(ResourceNameCollectionFactoryInterface::class);
            $names->method('create')->willReturn(new ResourceNameCollection(array_keys(self::SHORT_NAMES)));
        }
        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturnCallback(static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, [new ApiResource(shortName: self::SHORT_NAMES[$class])]));
        $registry = $this->createStub(ManagerRegistry::class);

        return new VersionedDocumentationNormalizer($decorated, $names, $metadata, new ExplicitAllowOnlyAttributeReader($registry), new RoutePrefixAttributeReader($registry));
    }

    private static function supportedClasses(string $key): array
    {
        return [$key => array_map(static fn (string $title): array => ['title' => $title], array_values(self::SHORT_NAMES))];
    }
}
