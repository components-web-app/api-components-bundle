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
use Silverback\ApiComponentsBundle\EventListener\Console\ConsoleOutputListener;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class ConsoleOutputListenerTest extends TestCase
{
    private function start(ConsoleOutputListener $listener, OutputInterface $output): void
    {
        $listener->onConsoleCommand(new ConsoleCommandEvent(new Command('any'), new ArrayInput([]), $output));
    }

    public function test_there_is_no_output_outside_a_command(): void
    {
        self::assertNull((new ConsoleOutputListener())->getOutput());
    }

    public function test_the_running_command_output_is_available_until_it_terminates(): void
    {
        $listener = new ConsoleOutputListener();
        $output = new BufferedOutput();

        $this->start($listener, $output);
        self::assertSame($output, $listener->getOutput());

        $listener->onConsoleTerminate();
        self::assertNull($listener->getOutput());
    }

    public function test_a_nested_command_does_not_take_the_output_from_the_outer_command(): void
    {
        $listener = new ConsoleOutputListener();
        $outer = new BufferedOutput();
        $inner = new BufferedOutput();

        $this->start($listener, $outer);
        $this->start($listener, $inner);
        self::assertSame($inner, $listener->getOutput());

        $listener->onConsoleTerminate();
        self::assertSame($outer, $listener->getOutput());
    }

    public function test_reset_forgets_every_output(): void
    {
        $listener = new ConsoleOutputListener();
        $this->start($listener, new BufferedOutput());

        $listener->reset();

        self::assertNull($listener->getOutput());
    }

    public function test_the_listener_is_wired_to_console_events_and_given_to_the_fixture_builder(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../../src/Resources/config')))->load('services.php');

        $listeners = $container->getDefinition('silverback.api_components.event_listener.console.console_output')->getTag('kernel.event_listener');
        self::assertSame([
            ['event' => ConsoleEvents::COMMAND, 'method' => 'onConsoleCommand'],
            ['event' => ConsoleEvents::TERMINATE, 'method' => 'onConsoleTerminate'],
        ], $listeners);

        $arguments = $container->getDefinition('silverback.api_components.fixture.cwa_fixture_builder')->getArguments();
        self::assertEquals(new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE), $arguments[5]);
        self::assertEquals(new Reference('silverback.api_components.event_listener.console.console_output'), $arguments[6]);
    }
}
