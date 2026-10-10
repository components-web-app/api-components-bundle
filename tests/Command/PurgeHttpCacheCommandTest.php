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
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class PurgeHttpCacheCommandTest extends TestCase
{
    private const string SERVICE_ID = 'silverback.api_components.command.purge_http_cache';
    private const string FLUSHER_ID = 'silverback.api_components.http_cache.flusher';
    private const string PURGER_ID = 'silverback.api_components.http_cache.purger';

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
        $container->set(self::PURGER_ID, $this->createStub(HttpCachePurger::class));

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
        $container->set(self::PURGER_ID, $this->createStub(HttpCachePurger::class));

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
        $container->set(self::PURGER_ID, $this->createStub(HttpCachePurger::class));

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
    }

    public function test_the_command_is_given_the_purger_explicitly_and_optionally(): void
    {
        $arguments = $this->loadContainer()->getDefinition(self::SERVICE_ID)->getArguments();

        self::assertCount(2, $arguments);
        self::assertSame(self::FLUSHER_ID, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(self::PURGER_ID, (string) $arguments[1]);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[1]->getInvalidBehavior());
    }

    public function test_the_tag_option_purges_exactly_the_given_tags_and_never_flushes(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->expects(self::once())->method('purgeTags')->with(['/_api/_/routes//about-us', 'cwa-html']);
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::SUCCESS, $tester->execute(['--tag' => ['/_api/_/routes//about-us', 'cwa-html']]));
        self::assertStringContainsString("Purged the HTTP cache tags:\n  /_api/_/routes//about-us\n  cwa-html\n", $tester->getDisplay());
    }

    public function test_the_path_option_purges_the_route_tag_of_each_path(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('getRouteTag')->willReturnCallback(static fn (string $path): string => '/_api/_/routes/' . $path);
        $purger->expects(self::once())->method('purgeTags')->with(['/_api/_/routes//about-us', '/_api/_/routes//contact']);
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::SUCCESS, $tester->execute(['--path' => ['/about-us', '/contact']]));
        self::assertStringContainsString('/_api/_/routes//contact', $tester->getDisplay());
    }

    public function test_tags_and_paths_are_purged_together_once_each(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('getRouteTag')->willReturnCallback(static fn (string $path): string => '/_api/_/routes/' . $path);
        $purger->expects(self::once())->method('purgeTags')->with(['/_api/_/routes//about-us', 'cwa-html']);
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::SUCCESS, $tester->execute(['--tag' => ['/_api/_/routes//about-us', 'cwa-html'], '--path' => ['/about-us']]));
    }

    public function test_a_repeated_tag_is_purged_once_in_a_list(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->expects(self::once())->method('purgeTags')->with(self::identicalTo(['/one', '/two']));
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::SUCCESS, $tester->execute(['--tag' => ['/one', '/one', '/two']]));
    }

    public function test_selected_tags_are_not_purged_and_the_command_succeeds_when_no_purger_is_configured(): void
    {
        $tester = $this->tester($this->neverFlushingFlusher(), null);

        self::assertSame(Command::SUCCESS, $tester->execute(['--tag' => ['cwa-html']]));
        self::assertStringContainsString('No HTTP cache purger is configured, so nothing was purged', $tester->getDisplay());
    }

    public function test_selected_tags_are_not_purged_and_the_command_succeeds_when_the_purger_cannot_purge_tags(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(false);
        $purger->expects(self::never())->method('purgeTags');
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::SUCCESS, $tester->execute(['--path' => ['/about-us']]));
        self::assertStringContainsString('No HTTP cache purger is configured, so nothing was purged', $tester->getDisplay());
    }

    public function test_the_command_fails_when_purging_selected_tags_fails(): void
    {
        $purger = $this->createStub(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('purgeTags')->willThrowException(new HttpCachePurgeFailedException('Failed to purge the HTTP cache tags "cwa-html": Connection refused'));
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::FAILURE, $tester->execute(['--tag' => ['cwa-html']]));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
        self::assertStringNotContainsString('Purged', $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, list<string>>, string}>
     */
    public static function invalidSelections(): iterable
    {
        yield 'an empty tag' => [['--tag' => ['']], 'A cache tag cannot be empty'];
        yield 'a tag containing a space' => [['--tag' => ['/one /two']], 'A cache tag cannot contain a space or a comma: "/one /two"'];
        yield 'a tag containing a tab' => [['--tag' => ["/one\t/two"]], 'A cache tag cannot contain a space or a comma'];
        yield 'a tag containing a comma' => [['--tag' => ['/one,/two']], 'A cache tag cannot contain a space or a comma: "/one,/two"'];
        yield 'a path without a leading slash' => [['--path' => ['about-us']], 'A path must start with "/": "about-us"'];
        yield 'an empty path' => [['--path' => ['']], 'A path must start with "/": ""'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSelections')]
    public function test_an_invalid_selection_purges_nothing_and_is_reported_as_invalid_input(array $input, string $message): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('getRouteTag')->willReturnCallback(static fn (string $path): string => '/_api/_/routes/' . $path);
        $purger->expects(self::never())->method('purgeTags');
        $tester = $this->tester($this->neverFlushingFlusher(), $purger);

        self::assertSame(Command::INVALID, $tester->execute($input));
        self::assertStringContainsString($message, $tester->getDisplay());
    }

    private function neverFlushingFlusher(): HttpCacheFlusher
    {
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn(true);
        $flusher->expects(self::never())->method('flush');

        return $flusher;
    }

    private function tester(HttpCacheFlusher $flusher, ?HttpCachePurger $purger): CommandTester
    {
        return new CommandTester(new PurgeHttpCacheCommand($flusher, $purger));
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_doctrine_orm_http_cache_purger.php');

        return $container;
    }
}
