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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Action\Health\HealthAction;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutingPhpFileLoader;

class HealthActionTest extends TestCase
{
    public function test_a_reachable_database_answers_ok(): void
    {
        $response = (new HealthAction($this->reachableConnection()))();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"status":"ok"}', $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function test_a_reachable_database_logs_nothing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method(self::anything());

        (new HealthAction($this->reachableConnection(), $logger))();
    }

    public function test_an_ok_response_is_never_stored(): void
    {
        $response = (new HealthAction($this->reachableConnection()))();

        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('max-age'));
    }

    public function test_an_unreachable_database_answers_503_with_a_short_reason(): void
    {
        $response = (new HealthAction($this->unreachableConnection()))();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('{"status":"unavailable","reason":"database"}', $response->getContent());
    }

    public function test_an_unavailable_response_is_never_stored(): void
    {
        $response = (new HealthAction($this->unreachableConnection()))();

        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('s-maxage'));
    }

    public function test_an_unreachable_database_is_logged_as_a_warning_with_the_exception(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with(self::isString(), self::callback(static fn (array $context): bool => $context['exception'] instanceof DBALException));

        (new HealthAction($this->unreachableConnection(), $logger))();
    }

    public function test_an_unreachable_database_answers_503_without_a_logger(): void
    {
        $response = (new HealthAction($this->unreachableConnection(), null))();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }

    public function test_an_error_that_is_not_a_database_error_is_not_swallowed(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $connection->method('executeQuery')->willThrowException(new \LogicException('not a database error'));

        $this->expectException(\LogicException::class);

        (new HealthAction($connection))();
    }

    public function test_the_action_is_wired_to_the_default_connection_and_an_optional_logger(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config'));
        $loader->load('services.php');

        $definition = $container->getDefinition(HealthAction::class);
        self::assertTrue($definition->hasTag('controller.service_arguments'));
        self::assertTrue($definition->isPublic());
        self::assertSame(HealthAction::class, (string) $container->getAlias('silverback.api_components.action.health'));

        [$connection, $logger] = $definition->getArguments();
        self::assertInstanceOf(Reference::class, $connection);
        self::assertSame('doctrine.dbal.default_connection', (string) $connection);
        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
    }

    public function test_the_route_answers_only_get_at_the_bundle_health_path(): void
    {
        $loader = new RoutingPhpFileLoader(new FileLocator(__DIR__ . '/../../../src/Resources/config/routing'));
        $route = $loader->load('health.php')->get('api_components_health');

        self::assertNotNull($route);
        self::assertSame('/_/health', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
        self::assertSame(HealthAction::class, $route->getDefault('_controller'));
    }

    private function reachableConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function unreachableConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => sys_get_temp_dir() . '/' . uniqid('missing-', true) . '/db.sqlite']);
    }
}
