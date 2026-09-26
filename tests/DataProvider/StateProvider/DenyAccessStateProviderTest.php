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
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DenyAccessStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Silverback\ApiComponentsBundle\Security\Voter\ComponentVoter;
use Silverback\ApiComponentsBundle\Security\Voter\RouteVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DenyAccessStateProviderTest extends TestCase
{
    public function test_a_readable_component_is_returned(): void
    {
        $component = new DummyComponent();
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->with(ComponentVoter::READ_COMPONENT, $component)->willReturn(true);

        self::assertSame($component, $this->provider($component, $security)->provide(new Get()));
    }

    public function test_a_component_that_is_not_readable_is_denied(): void
    {
        $component = new DummyComponent();
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Component access denied.');
        $this->provider($component, $security)->provide(new Get());
    }

    public function test_page_data_with_no_route_is_returned_without_a_route_check(): void
    {
        $pageData = new class extends AbstractPageData {};
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        self::assertSame($pageData, $this->provider($pageData, $security, [])->provide(new Get()));
    }

    public function test_page_data_is_returned_when_any_of_its_routes_is_readable(): void
    {
        $pageData = new class extends AbstractPageData {};
        $gated = new Route();
        $live = new Route();
        $security = $this->createMock(Security::class);
        $security->expects(self::exactly(2))->method('isGranted')->willReturnCallback(
            static fn (string $attribute, Route $route): bool => RouteVoter::READ_ROUTE === $attribute && $route === $live
        );

        self::assertSame($pageData, $this->provider($pageData, $security, [$gated, $live])->provide(new Get()));
    }

    public function test_page_data_is_denied_when_none_of_its_routes_is_readable(): void
    {
        $pageData = new class extends AbstractPageData {};
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Page data access denied.');
        $this->provider($pageData, $security, [new Route()])->provide(new Get());
    }

    public function test_any_other_result_is_returned_unchecked(): void
    {
        $route = new Route();
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('isGranted');

        self::assertSame($route, $this->provider($route, $security)->provide(new Get()));
        self::assertNull($this->provider(null, $security)->provide(new Get()));
    }

    public function test_the_inner_provider_receives_the_operation_uri_variables_and_context(): void
    {
        $operation = new Get();
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['id' => 1], ['resource_class' => Route::class])->willReturn(null);

        (new DenyAccessStateProvider($inner, $this->createStub(Security::class), $this->createStub(RouteRepository::class)))
            ->provide($operation, ['id' => 1], ['resource_class' => Route::class]);
    }

    /**
     * @param list<Route>|null $routes
     */
    private function provider(?object $data, Security $security, ?array $routes = null): DenyAccessStateProvider
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);
        $routeRepository = $this->createMock(RouteRepository::class);
        if (null === $routes) {
            $routeRepository->expects(self::never())->method('findByPageData');
        } else {
            $routeRepository->expects(self::once())->method('findByPageData')->willReturn($routes);
        }

        return new DenyAccessStateProvider($inner, $security, $routeRepository);
    }
}
