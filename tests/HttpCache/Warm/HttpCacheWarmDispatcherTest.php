<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache\Warm;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\HttpCache\Warm\HttpCacheWarmDispatcher;
use Silverback\ApiComponentsBundle\Message\WarmHttpCacheMessage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

class HttpCacheWarmDispatcherTest extends TestCase
{
    private const string SERVICE_ID = 'silverback.api_components.http_cache.warm_dispatcher';

    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function test_the_dispatcher_is_registered_on_the_default_bus(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config')))->load('services_doctrine_orm_http_cache_purger.php');

        $definition = $container->getDefinition(self::SERVICE_ID);
        self::assertSame(HttpCacheWarmDispatcher::class, $definition->getClass());
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(HttpCacheWarmDispatcher::class));
        [$bus, $logger] = $definition->getArguments();
        self::assertSame('messenger.default_bus', (string) $bus);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $bus->getInvalidBehavior());
        self::assertSame('logger', (string) $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
    }

    public function test_it_dispatches_one_warm_for_server_side_rendering_and_one_for_browsers(): void
    {
        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        (new HttpCacheWarmDispatcher($bus, $this->logger()))->dispatch();

        self::assertEquals([new WarmHttpCacheMessage('gzip'), new WarmHttpCacheMessage('br')], $dispatched);
        self::assertSame(['gzip', 'br'], HttpCacheWarmDispatcher::ACCEPT_ENCODINGS);
    }

    public function test_a_failed_dispatch_is_logged_at_error_and_the_next_encoding_is_still_dispatched(): void
    {
        $calls = 0;
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$calls): Envelope {
            if (0 === $calls++) {
                throw new TransportException('The transport is down');
            }

            return new Envelope($message);
        });

        (new HttpCacheWarmDispatcher($bus, $this->logger()))->dispatch();

        self::assertSame(2, $calls);
        self::assertTrue($this->logs->hasRecordThatContains('The HTTP cache warm with Accept-Encoding gzip could not be dispatched after the flush; the flush itself succeeded: The transport is down', Level::Error));
        self::assertCount(1, $this->logs->getRecords());
    }

    public function test_with_no_message_bus_nothing_is_warmed_and_a_warning_says_why(): void
    {
        (new HttpCacheWarmDispatcher(null, $this->logger()))->dispatch();

        self::assertTrue($this->logs->hasRecordThatContains('http_cache.warm_after_flush is enabled but no message bus is configured, so the HTTP cache was not warmed after the flush', Level::Warning));
    }

    public function test_it_needs_no_logger(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new TransportException('down'));

        (new HttpCacheWarmDispatcher($bus))->dispatch();
        (new HttpCacheWarmDispatcher(null))->dispatch();

        $this->expectNotToPerformAssertions();
    }

    private function logger(): Logger
    {
        return new Logger('test', [$this->logs]);
    }
}
