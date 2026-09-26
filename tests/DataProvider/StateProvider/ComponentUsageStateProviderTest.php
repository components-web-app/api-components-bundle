<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentUsageStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\HttpFoundation\Request;

class ComponentUsageStateProviderTest extends TestCase
{
    public function test_the_usage_operation_returns_the_usage_metadata_of_the_component_and_sets_it_as_the_request_data(): void
    {
        $component = new DummyComponent();
        $usage = new ComponentUsageMetadata(2, 1);
        $factory = $this->createMock(ComponentUsageMetadataFactory::class);
        $factory->expects(self::once())->method('create')->with($component)->willReturn($usage);
        $request = new Request();
        $request->attributes->set('data', $component);

        $result = $this->provider($component, $factory)->provide(new Get(name: '_api_/component/dummy_components/{id}/usage_get_usage'), [], ['request' => $request]);

        self::assertSame($usage, $result);
        self::assertSame($usage, $request->attributes->get('data'));
    }

    public function test_the_usage_operation_without_a_request_still_returns_the_usage_metadata(): void
    {
        $component = new DummyComponent();
        $usage = new ComponentUsageMetadata(0, 0);
        $factory = $this->createStub(ComponentUsageMetadataFactory::class);
        $factory->method('create')->willReturn($usage);

        self::assertSame($usage, $this->provider($component, $factory)->provide(new Get(name: 'x_get_usage')));
    }

    public function test_another_operation_on_a_component_returns_the_component(): void
    {
        $component = new DummyComponent();
        $factory = $this->createMock(ComponentUsageMetadataFactory::class);
        $factory->expects(self::never())->method('create');
        $request = new Request();

        self::assertSame($component, $this->provider($component, $factory)->provide(new Get(name: '_api_/component/dummy_components/{id}_get'), [], ['request' => $request]));
        self::assertFalse($request->attributes->has('data'));
    }

    public function test_the_usage_operation_name_on_something_that_is_not_a_component_returns_it_unchanged(): void
    {
        $route = new Route();
        $factory = $this->createMock(ComponentUsageMetadataFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertSame($route, $this->provider($route, $factory)->provide(new Get(name: 'x_get_usage')));
        self::assertNull($this->provider(null, $factory)->provide(new Get(name: 'x_get_usage')));
    }

    public function test_the_inner_provider_receives_the_operation_uri_variables_and_context(): void
    {
        $operation = new Get(name: 'x_get_usage');
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['id' => 1], ['resource_class' => Route::class])->willReturn(null);

        (new ComponentUsageStateProvider($inner, $this->createStub(ComponentUsageMetadataFactory::class)))->provide($operation, ['id' => 1], ['resource_class' => Route::class]);
    }

    private function provider(?object $data, ComponentUsageMetadataFactory $factory): ComponentUsageStateProvider
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return new ComponentUsageStateProvider($inner, $factory);
    }
}
