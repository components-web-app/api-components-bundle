<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\ComponentResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UserResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DownloadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UploadStateProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class StateOperationMetadataTest extends TestCase
{
    public function test_the_usage_operation_is_named_after_its_key_so_providers_can_recognise_it(): void
    {
        $segments = $this->createStub(PathSegmentNameGeneratorInterface::class);
        $segments->method('getSegmentName')->willReturn('dummy_components');
        $factory = new ComponentResourceMetadataFactory($this->decorated(DummyComponent::class, 'DummyComponent'), $segments);

        $operation = $factory->create(DummyComponent::class)->getOperation('_api_/dummy_components/{id}/usage_get_usage');

        self::assertSame('_api_/dummy_components/{id}/usage_get_usage', $operation->getName());
        self::assertSame('/dummy_components/{id}/usage', $operation->getUriTemplate());
        self::assertSame('main_controller', $operation->getController());
    }

    public function test_the_me_operation_keeps_the_controller_of_the_get_operation_it_is_made_from(): void
    {
        $factory = new UserResourceMetadataCollectionFactory($this->decorated(User::class, 'User'));

        $operation = $factory->create(User::class)->getOperation('_api_me');

        self::assertSame('_api_me', $operation->getName());
        self::assertSame('main_controller', $operation->getController());
    }

    public function test_the_upload_and_download_operations_are_marked_for_their_providers_and_keep_the_main_controller(): void
    {
        $reader = $this->createStub(UploadableAttributeReaderInterface::class);
        $reader->method('isConfigured')->willReturn(true);
        $reader->method('getConfiguredProperties')->willReturn([]);
        $segments = $this->createStub(PathSegmentNameGeneratorInterface::class);
        $segments->method('getSegmentName')->willReturn('dummy_uploadables');
        $factory = new UploadableResourceMetadataCollectionFactory($this->decorated(DummyUploadable::class, 'DummyUploadable', ['kept' => 1]), $reader, $segments);

        $metadata = $factory->create(DummyUploadable::class);
        $upload = $metadata->getOperation('_api_/dummy_uploadables/{id}/upload_post');
        $download = $metadata->getOperation('_api_/dummy_uploadables/{id}/download/{property}_get');

        self::assertSame(['kept' => 1, UploadStateProvider::OPERATION_EXTRA_PROPERTY => true], $upload->getExtraProperties());
        self::assertSame(['kept' => 1, DownloadStateProvider::OPERATION_EXTRA_PROPERTY => true], $download->getExtraProperties());
        self::assertSame('main_controller', $upload->getController());
        self::assertSame('main_controller', $download->getController());
        self::assertFalse($upload->canDeserialize());
        self::assertFalse($download->canSerialize());
    }

    /**
     * @param array<string, mixed> $extraProperties
     */
    private function decorated(string $class, string $shortName, array $extraProperties = []): ResourceMetadataCollectionFactoryInterface
    {
        $get = new Get(uriTemplate: '/items/{id}', uriVariables: ['id'], shortName: $shortName, class: $class, name: 'item_get', controller: 'main_controller', extraProperties: $extraProperties);
        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection($class, [
            (new ApiResource(shortName: $shortName, class: $class))->withOperations(new Operations(['item_get' => $get])),
        ]));

        return $decorated;
    }
}
