<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Console;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\EventListener\Console\FixtureLoadListener;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FixtureLoadListenerTest extends TestCase
{
    private const string NO_FLUSH = 'The configured HTTP cache purger cannot flush the whole cache, so nothing was flushed';
    private const string IMAGINE_HINT = 'Image variants are not generated while fixtures load. Run silverback:api-components:imagine:generate to generate the image variants.';

    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();
    }

    private function start(FixtureLoadListener $listener, string $name): void
    {
        $listener->onConsoleCommand(new ConsoleCommandEvent(new Command($name), new ArrayInput([]), $this->output));
    }

    private function startWithoutCommand(FixtureLoadListener $listener): void
    {
        $listener->onConsoleCommand(new ConsoleCommandEvent(null, new ArrayInput([]), $this->output));
    }

    private function terminate(FixtureLoadListener $listener, string $name, int $exitCode = Command::SUCCESS): void
    {
        $listener->onConsoleTerminate(new ConsoleTerminateEvent(new Command($name), new ArrayInput([]), $this->output, $exitCode));
    }

    private function flusher(bool $canFlush, int $flushes, ?\Throwable $failure = null): HttpCacheFlusher
    {
        $flusher = $this->createMock(HttpCacheFlusher::class);
        $flusher->method('canFlush')->willReturn($canFlush);
        $expectation = $flusher->expects(self::exactly($flushes))->method('flush');
        if (null !== $failure) {
            $expectation->willThrowException($failure);
        }

        return $flusher;
    }

    public function test_nothing_is_loading_outside_a_command(): void
    {
        self::assertFalse((new FixtureLoadListener())->isLoadingFixtures());
    }

    public function test_fixtures_are_loading_from_the_start_of_the_load_command_until_it_terminates(): void
    {
        $listener = new FixtureLoadListener();

        $this->start($listener, 'doctrine:fixtures:load');
        self::assertTrue($listener->isLoadingFixtures());

        $this->terminate($listener, 'doctrine:fixtures:load');
        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_another_command_is_not_a_fixture_load(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 0), true);

        $this->start($listener, 'doctrine:fixtures:list');
        self::assertFalse($listener->isLoadingFixtures());
        $this->terminate($listener, 'doctrine:fixtures:list');

        self::assertSame('', $this->output->fetch());
    }

    public function test_an_event_with_no_command_is_not_a_fixture_load(): void
    {
        $listener = new FixtureLoadListener();

        $this->startWithoutCommand($listener);

        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_a_command_nested_in_a_load_neither_ends_the_load_nor_flushes(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1));

        $this->start($listener, 'doctrine:fixtures:load');
        $this->start($listener, 'cache:clear');
        $this->terminate($listener, 'cache:clear');
        self::assertTrue($listener->isLoadingFixtures());
        self::assertSame('', $this->output->fetch());

        $this->terminate($listener, 'doctrine:fixtures:load');
        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_a_load_nested_in_a_load_flushes_once_when_the_outer_load_terminates(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1));

        $this->start($listener, 'doctrine:fixtures:load');
        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');
        self::assertTrue($listener->isLoadingFixtures());

        $this->terminate($listener, 'doctrine:fixtures:load');
        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_a_load_nested_in_another_command_ends_when_it_terminates(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1));

        $this->start($listener, 'app:reset');
        $this->start($listener, 'doctrine:fixtures:load');
        self::assertTrue($listener->isLoadingFixtures());

        $this->terminate($listener, 'doctrine:fixtures:load');
        self::assertFalse($listener->isLoadingFixtures());

        $this->terminate($listener, 'app:reset');
        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_the_end_of_a_load_flushes_the_http_cache_and_says_so(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1));

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');

        self::assertSame("Flushed the HTTP cache\n", $this->output->fetch());
    }

    public function test_a_failed_load_still_flushes_the_http_cache(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1), true);

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load', Command::FAILURE);

        self::assertSame("Flushed the HTTP cache\n", $this->output->fetch());
    }

    public function test_a_purger_that_cannot_flush_is_reported_and_never_asked_to(): void
    {
        $listener = new FixtureLoadListener($this->flusher(false, 0));

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');

        self::assertSame(self::NO_FLUSH . "\n", $this->output->fetch());
    }

    public function test_no_flusher_is_reported_as_nothing_flushed(): void
    {
        $listener = new FixtureLoadListener();

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');

        self::assertSame(self::NO_FLUSH . "\n", $this->output->fetch());
    }

    public function test_a_failed_flush_is_logged_and_reported_without_failing_the_load(): void
    {
        $failure = new HttpCacheFlushFailedException('Failed to flush the HTTP cache at http://cache/flush: the cache responded with 500');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'The HTTP cache could not be flushed after loading fixtures: {message}',
            ['message' => $failure->getMessage(), 'exception' => $failure],
        );
        $listener = new FixtureLoadListener($this->flusher(true, 1, $failure), true, $logger);

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');

        self::assertSame($failure->getMessage() . "\n" . self::IMAGINE_HINT . "\n", $this->output->fetch());
        self::assertFalse($listener->isLoadingFixtures());
    }

    public function test_with_imagine_a_successful_load_says_to_generate_the_variants(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 1), true);

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load');

        self::assertSame("Flushed the HTTP cache\n" . self::IMAGINE_HINT . "\n", $this->output->fetch());
    }

    public function test_with_imagine_a_failed_load_does_not_say_to_generate_the_variants(): void
    {
        $listener = new FixtureLoadListener(null, true);

        $this->start($listener, 'doctrine:fixtures:load');
        $this->terminate($listener, 'doctrine:fixtures:load', Command::FAILURE);

        self::assertSame(self::NO_FLUSH . "\n", $this->output->fetch());
    }

    public function test_reset_ends_a_load_without_flushing(): void
    {
        $listener = new FixtureLoadListener($this->flusher(true, 0));
        $this->start($listener, 'doctrine:fixtures:load');

        $listener->reset();

        self::assertFalse($listener->isLoadingFixtures());
        self::assertSame('', $this->output->fetch());
    }

    public function test_the_listener_is_wired_to_console_events_reset_and_given_to_the_update_propagation(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config'));
        $loader->load('services.php');
        $id = 'silverback.api_components.event_listener.console.fixture_load';

        $definition = $container->getDefinition($id);
        self::assertSame(FixtureLoadListener::class, $definition->getClass());
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame([
            ['event' => ConsoleEvents::COMMAND, 'method' => 'onConsoleCommand'],
            ['event' => ConsoleEvents::TERMINATE, 'method' => 'onConsoleTerminate'],
        ], $definition->getTag('kernel.event_listener'));
        self::assertSame([['method' => 'reset']], $definition->getTag('kernel.reset'));
        self::assertEquals([
            '$httpCacheFlusher' => new Reference('silverback.api_components.http_cache.flusher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '$imagineEnabled' => false,
            '$logger' => new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $definition->getArguments());
        self::assertSame($id, (string) $container->getAlias(FixtureLoadListener::class));

        $propagation = $container->getDefinition('silverback.api_components.event_listener.doctrine.propagate_updates_listener')->getArguments();
        self::assertEquals(new Reference($id), $propagation[7]);
    }
}
