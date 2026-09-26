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

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\RouteGenerateStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;

class RouteGenerateStateProviderTest extends TestCase
{
    public function test_the_generate_operation_generates_the_route_for_its_page_and_sets_it_as_the_request_data(): void
    {
        $page = new Page();
        $route = (new Route())->setPage($page);
        $generated = new Route();
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::once())->method('create')->with($page, $route)->willReturn($generated);
        $request = new Request();

        $result = $this->provider($route, $generator)->provide($this->generate(), [], ['request' => $request]);

        self::assertSame($generated, $result);
        self::assertSame($generated, $request->attributes->get('data'));
    }

    public function test_page_data_is_preferred_over_the_page(): void
    {
        $pageData = new class extends AbstractPageData {};
        $route = (new Route())->setPage(new Page())->setPageData($pageData);
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::once())->method('create')->with($pageData, $route)->willReturn($route);

        self::assertSame($route, $this->provider($route, $generator)->provide($this->generate()));
    }

    public function test_a_route_with_no_page_is_a_logic_error_because_validation_runs_first(): void
    {
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::never())->method('create');

        $this->expectException(\LogicException::class);
        $this->provider(new Route(), $generator)->provide($this->generate());
    }

    public function test_another_operation_on_a_route_is_left_alone(): void
    {
        $route = (new Route())->setPage(new Page());
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::never())->method('create');
        $request = new Request();

        self::assertSame($route, $this->provider($route, $generator)->provide(new Post(name: '_api_/routes{._format}_post'), [], ['request' => $request]));
        self::assertFalse($request->attributes->has('data'));
    }

    public function test_the_generate_operation_with_something_other_than_a_route_is_left_alone(): void
    {
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::never())->method('create');

        self::assertNull($this->provider(null, $generator)->provide($this->generate()));
    }

    public function test_the_inner_provider_receives_the_operation_uri_variables_and_context(): void
    {
        $operation = $this->generate();
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['a' => 1], ['b' => 2])->willReturn(null);

        (new RouteGenerateStateProvider($inner, $this->createStub(RouteGeneratorInterface::class)))->provide($operation, ['a' => 1], ['b' => 2]);
    }

    private function generate(): Post
    {
        return new Post(name: '_api_/routes/generate{._format}_post');
    }

    private function provider(?object $data, RouteGeneratorInterface $generator): RouteGenerateStateProvider
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return new RouteGenerateStateProvider($inner, $generator);
    }
}
