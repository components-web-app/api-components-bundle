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
use Silverback\ApiComponentsBundle\Command\PurgeRenderedHtmlCommand;
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PurgeRenderedHtmlCommandTest extends TestCase
{
    private const string SERVICE_ID = 'silverback.api_components.command.purge_rendered_html';
    private const string PURGER_ID = 'silverback.api_components.http_cache.purger';

    public function test_the_command_is_registered_as_a_console_command_under_its_service_id(): void
    {
        $container = $this->loadContainer();

        self::assertTrue($container->getDefinition(self::SERVICE_ID)->hasTag('console.command'));
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(PurgeRenderedHtmlCommand::class));
    }

    public function test_the_command_built_from_its_service_definition_purges_the_rendered_html(): void
    {
        $container = $this->loadContainer();
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::once())->method('purgeRenderedHtml');
        $purger->expects(self::never())->method('propagate');
        $container->set(self::PURGER_ID, $purger);

        $command = $container->get(self::SERVICE_ID);
        self::assertInstanceOf(PurgeRenderedHtmlCommand::class, $command);
        self::assertSame('silverback:api-components:purge-rendered-html', $command->getName());

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function test_the_command_fails_with_the_reason_when_the_purge_fails(): void
    {
        $container = $this->loadContainer();
        $purger = $this->createStub(HttpCachePurger::class);
        $purger->method('purgeRenderedHtml')->willThrowException(new HttpCachePurgeFailedException('Failed to purge the HTTP cache tags "cwa-html": Could not resolve host: souin'));
        $container->set(self::PURGER_ID, $purger);

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Could not resolve host: souin', $tester->getDisplay());
        self::assertStringNotContainsString('Purged', $tester->getDisplay());
    }

    public function test_the_command_succeeds_without_purging_when_no_http_cache_purger_is_configured(): void
    {
        $container = $this->loadContainer();
        $container->removeDefinition(self::PURGER_ID);

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('nothing was purged', $tester->getDisplay());
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_doctrine_orm_http_cache_purger.php');

        return $container;
    }
}
