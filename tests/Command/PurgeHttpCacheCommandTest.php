<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\PurgeHttpCacheCommand;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PurgeHttpCacheCommandTest extends TestCase
{
    private const string SERVICE_ID = 'silverback.api_components.command.purge_http_cache';
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';

    public function test_the_command_is_registered_as_a_console_command_under_its_service_id(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->getDefinition(self::SERVICE_ID)->hasTag('console.command'));
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(PurgeHttpCacheCommand::class));
    }

    public function test_the_command_flushes_the_http_cache(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::once())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);

        $command = $container->get(self::SERVICE_ID);
        self::assertInstanceOf(PurgeHttpCacheCommand::class, $command);
        self::assertSame('silverback:api-components:purge-http-cache', $command->getName());

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Flushed', $tester->getDisplay());
    }

    public function test_the_command_succeeds_and_says_it_cannot_flush_when_the_purger_has_no_flush(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(false);
        $flusher->expects(self::never())->method('flush');
        $container->set(self::FLUSHER_ID, $flusher);

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('cannot flush', $tester->getDisplay());
    }

    public function test_the_command_fails_when_the_flush_request_fails(): void
    {
        $container = $this->loadContainer();
        $flusher = $this->createStub(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->method('flush')->willThrowException(new HttpCacheFlushFailedException('Connection refused'));
        $container->set(self::FLUSHER_ID, $flusher);

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_doctrine_orm_http_cache_purger.php');

        return $container;
    }
}
