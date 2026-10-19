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
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\ComponentResourceMetadataFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UserResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentMoveStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DownloadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UploadStateProvider;
use Silverback\ApiComponentsBundle\Model\Component\ComponentMove;
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
        self::assertSame(['public' => false, 'no_store' => true], $operation->getCacheHeaders());
        self::assertSame('main_controller', $operation->getController());
    }

    public function test_the_move_operation_is_a_post_of_a_move_under_the_components_iri_through_the_main_controller(): void
    {
        $segments = $this->createStub(PathSegmentNameGeneratorInterface::class);
        $segments->method('getSegmentName')->willReturn('dummy_components');
        $factory = new ComponentResourceMetadataFactory($this->decorated(DummyComponent::class, 'DummyComponent'), $segments);

        $operation = $factory->create(DummyComponent::class)->getOperation('_api_/dummy_components/{id}/move_post_move');

        self::assertInstanceOf(HttpOperation::class, $operation);
        self::assertSame('_api_/dummy_components/{id}/move_post_move', $operation->getName());
        self::assertSame('/dummy_components/{id}/move', $operation->getUriTemplate());
        self::assertSame('POST', $operation->getMethod());
        self::assertSame(200, $operation->getStatus());
        self::assertSame(['id'], $operation->getUriVariables());
        self::assertSame('main_controller', $operation->getController());
        self::assertTrue($operation->canRead());
        self::assertTrue($operation->canDeserialize());
        self::assertTrue($operation->canValidate());
        self::assertTrue($operation->canWrite());
        self::assertSame(ComponentMove::class, $operation->getInput()['class'] ?? null);
        self::assertSame(ComponentMove::class, $operation->getOutput()['class'] ?? null);
        self::assertSame(['groups' => ['ComponentMove:read'], 'skip_null_values' => false], $operation->getNormalizationContext());
        self::assertSame(['groups' => ['ComponentMove:write'], 'api_assign_object_to_populate' => false], $operation->getDenormalizationContext());
        self::assertSame(ComponentMoveStateProcessor::class, $operation->getProcessor());
    }

    public function test_a_resource_that_is_not_a_component_gets_no_move_operation(): void
    {
        $segments = $this->createStub(PathSegmentNameGeneratorInterface::class);
        $segments->method('getSegmentName')->willReturn('users');
        $factory = new ComponentResourceMetadataFactory($this->decorated(User::class, 'User'), $segments);

        self::assertSame(['item_get'], array_keys(iterator_to_array($factory->create(User::class)[0]->getOperations() ?? [])));
    }

    public function test_the_me_operation_keeps_the_controller_of_the_get_operation_it_is_made_from(): void
    {
        $factory = new UserResourceMetadataCollectionFactory($this->decorated(User::class, 'User'));

        $operation = $factory->create(User::class)->getOperation('_api_me');

        self::assertSame('_api_me', $operation->getName());
        self::assertSame('main_controller', $operation->getController());
    }

    public function test_a_user_put_merges_into_the_stored_user_instead_of_replacing_it(): void
    {
        $get = new Get(uriTemplate: '/items/{id}', uriVariables: ['id'], shortName: 'User', class: User::class, name: 'item_get', extraProperties: ['kept' => 1]);
        $put = new Put(uriTemplate: '/items/{id}', uriVariables: ['id'], shortName: 'User', class: User::class, name: 'item_put', extraProperties: ['kept' => 1, 'standard_put' => true]);
        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection(User::class, [
            (new ApiResource(shortName: 'User', class: User::class))->withOperations(new Operations(['item_get' => $get, 'item_put' => $put])),
        ]));
        $factory = new UserResourceMetadataCollectionFactory($decorated);

        $metadata = $factory->create(User::class);

        self::assertSame(['kept' => 1, 'standard_put' => false], $metadata->getOperation('item_put')->getExtraProperties());
        self::assertSame(['kept' => 1], $metadata->getOperation('item_get')->getExtraProperties());
    }

    public function test_a_put_on_a_resource_that_is_not_a_user_is_left_alone(): void
    {
        $put = new Put(uriTemplate: '/items/{id}', uriVariables: ['id'], shortName: 'DummyComponent', class: DummyComponent::class, name: 'item_put', extraProperties: ['standard_put' => true]);
        $decorated = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $decorated->method('create')->willReturn(new ResourceMetadataCollection(DummyComponent::class, [
            (new ApiResource(shortName: 'DummyComponent', class: DummyComponent::class))->withOperations(new Operations(['item_put' => $put])),
        ]));
        $factory = new UserResourceMetadataCollectionFactory($decorated);

        self::assertSame(['standard_put' => true], $factory->create(DummyComponent::class)->getOperation('item_put')->getExtraProperties());
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
