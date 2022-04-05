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

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutingPrefixResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class RoutingPrefixResourceMetadataCollectionFactoryTest extends TestCase
{
    private function getDecoratedMock(?ResourceMetadataCollection $resourceMetadata = null)
    {
        $mock = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $mock
            ->expects(self::once())
            ->method('create')
            ->willReturn($resourceMetadata ?? new ResourceMetadataCollection('ResourceClassName'));

        return $mock;
    }

    public function test_component_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataCollectionFactory($decoratedMock);
        $metadataCollection = $factory->create(Form::class);
        /** @var ApiResource $apiResource */
        foreach ($metadataCollection as $apiResource) {
            $operations = $apiResource->getOperations();
            foreach ($operations as $operation) {
                $this->assertEquals('/component', $operation->getRoutePrefix());
            }
        }
    }

    public function test_page_data_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataCollectionFactory($decoratedMock);
        $pageDataClass = new class() extends AbstractPageData {
        };
        $metadataCollection = $factory->create(\get_class($pageDataClass));
        /** @var ApiResource $apiResource */
        foreach ($metadataCollection as $apiResource) {
            $operations = $apiResource->getOperations();
            foreach ($operations as $operation) {
                $this->assertEquals('/page_data', $operation->getRoutePrefix());
            }
        }
    }

    public function test_api_components_bundle_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataCollectionFactory($decoratedMock);
        $metadataCollection = $factory->create(Route::class);
        /** @var ApiResource $apiResource */
        foreach ($metadataCollection as $apiResource) {
            $operations = $apiResource->getOperations();
            foreach ($operations as $operation) {
                $this->assertEquals('/_', $operation->getRoutePrefix());
            }
        }
    }

    public function test_append_prefix_to_user_defined(): void
    {
        $operation1 = (new Operation(Operation::METHOD_GET, '', 'op'))->withRoutePrefix('/custom_prefix_op');
        $operation2 = (new Operation());
        $operations = new Operations([$operation1, $operation2]);
        $apiResource = (new ApiResource())
            ->withOperations($operations)
            ->withRoutePrefix('/custom_prefix');
        $resourceMetadata = new ResourceMetadataCollection(DummyComponent::class, [$apiResource]);
        $decoratedMock = $this->getDecoratedMock($resourceMetadata);
        $factory = new RoutingPrefixResourceMetadataCollectionFactory($decoratedMock);
        $metadataCollection = $factory->create(DummyComponent::class);
        /** @var ApiResource $apiResource */
        foreach ($metadataCollection as $apiResource) {
            $operations = $apiResource->getOperations();
            foreach ($operations as $operation) {
                if ('op' === $operation->getShortName()) {
                    $this->assertEquals('/component/custom_prefix_op', $operation->getRoutePrefix());
                    continue;
                }
                $this->assertEquals('/component/custom_prefix', $operation->getRoutePrefix());
            }
        }
    }

    public function test_no_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataCollectionFactory($decoratedMock);
        $metadataCollection = $factory->create(User::class);
        /** @var ApiResource $apiResource */
        foreach ($metadataCollection as $apiResource) {
            $operations = $apiResource->getOperations();
            foreach ($operations as $operation) {
                $this->assertNull($operation->getRoutePrefix());
            }
        }
    }
}
