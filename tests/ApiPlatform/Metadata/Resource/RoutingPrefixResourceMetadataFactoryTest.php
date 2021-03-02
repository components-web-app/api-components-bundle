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

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\RoutingPrefixResourceMetadataFactory;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class RoutingPrefixResourceMetadataFactoryTest extends TestCase
{
    /**
     * @var ResourceMetadataFactoryInterface|MockObject
     */
    private $decoratedMock;

    private function getDecoratedMock(?ResourceMetadata $resourceMetadata = null)
    {
        $mock = $this->createMock(ResourceMetadataFactoryInterface::class);
        $mock
            ->expects(self::once())
            ->method('create')
            ->willReturn($resourceMetadata ?? new ResourceMetadata());

        return $mock;
    }

    public function test_component_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataFactory($decoratedMock);
        $result = $factory->create(Form::class);
        $this->assertEquals('/component', $result->getAttribute('route_prefix'));
    }

    public function test_page_data_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataFactory($decoratedMock);
        $pageDataClass = new class() extends AbstractPageData {
        };
        $result = $factory->create(\get_class($pageDataClass));
        $this->assertEquals('/page_data', $result->getAttribute('route_prefix'));
    }

    public function test_api_components_bundle_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataFactory($decoratedMock);
        $result = $factory->create(Route::class);
        $this->assertEquals('/_', $result->getAttribute('route_prefix'));
    }

    public function test_append_prefix_to_user_defined(): void
    {
        $resourceMetadata = new ResourceMetadata(null, null, null, null, null, ['route_prefix' => '/custom_prefix/']);
        $decoratedMock = $this->getDecoratedMock($resourceMetadata);
        $factory = new RoutingPrefixResourceMetadataFactory($decoratedMock);
        $result = $factory->create(DummyComponent::class);
        $this->assertEquals('/component/custom_prefix', $result->getAttribute('route_prefix'));
    }

    public function test_no_prefix(): void
    {
        $decoratedMock = $this->getDecoratedMock();
        $factory = new RoutingPrefixResourceMetadataFactory($decoratedMock);
        $result = $factory->create(User::class);
        $this->assertNull($result->getAttribute('route_prefix'));
    }
}
