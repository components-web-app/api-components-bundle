<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Action\Health;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Action\Health\HealthAction;
use Silverback\ApiComponentsBundle\Action\Health\LivenessAction;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;

class LivenessActionTest extends TestCase
{
    public function test_it_answers_ok(): void
    {
        $response = (new LivenessAction())();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function test_it_is_never_stored(): void
    {
        $response = (new LivenessAction())();

        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
    }

    public function test_it_depends_on_nothing(): void
    {
        self::assertNull((new \ReflectionClass(LivenessAction::class))->getConstructor());
    }

    public function test_the_action_is_wired_with_no_arguments(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config'));
        $loader->load('services.php');

        $definition = $container->getDefinition(LivenessAction::class);
        self::assertTrue($definition->hasTag('controller.service_arguments'));
        self::assertTrue($definition->isPublic());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([], $definition->getArguments());
        self::assertSame(LivenessAction::class, (string) $container->getAlias('silverback.api_components.action.health_live'));
    }

    public function test_the_route_answers_only_get_at_the_bundle_liveness_path(): void
    {
        $loader = new RoutingPhpFileLoader(new FileLocator(__DIR__ . '/../../../src/Resources/config/routing'));
        $routes = $loader->load('health.php');

        $route = $routes->get('api_components_health_live');
        self::assertNotNull($route);
        self::assertSame('/_/health/live', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
        self::assertSame(LivenessAction::class, $route->getDefault('_controller'));

        $readiness = $routes->get('api_components_health');
        self::assertNotNull($readiness);
        self::assertSame('/_/health', $readiness->getPath());
        self::assertSame(HealthAction::class, $readiness->getDefault('_controller'));
    }
}
