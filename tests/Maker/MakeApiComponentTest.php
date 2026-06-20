<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Maker;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Maker\MakeApiComponent;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeApiComponentTest extends TestCase
{
    private function makeMaker(): MakeApiComponent
    {
        return new MakeApiComponent();
    }

    private function configuredCommand(): Command
    {
        $maker = $this->makeMaker();
        $command = new Command('make:api-component');
        $maker->configureCommand($command, new InputConfiguration());

        return $command;
    }

    private function boundInput(array $params): ArrayInput
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput($params, $command->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function makeIo(BufferedOutput $output): ConsoleStyle
    {
        return new ConsoleStyle(new ArrayInput([]), $output);
    }

    private function makeGenerator(string $expectedClass, array &$capturedVars): Generator
    {
        $generator = $this->createMock(Generator::class);

        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails($expectedClass, 'Entity\\Component\\'));

        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/' . basename($className) . '.php';
            });

        $generator->expects($this->once())->method('writeChanges');

        return $generator;
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:api-component', MakeApiComponent::getCommandName());
    }

    public function test_command_description(): void
    {
        $this->assertNotEmpty(MakeApiComponent::getCommandDescription());
    }

    public function test_generates_minimal_component(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertFalse($vars['timestamped']);
        $this->assertFalse($vars['publishable']);
        $this->assertFalse($vars['uploadable']);
    }

    public function test_generates_with_timestamped(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => true, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertTrue($vars['timestamped']);
        $this->assertFalse($vars['publishable']);
        $this->assertFalse($vars['uploadable']);
    }

    public function test_generates_with_publishable(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => true, '--uploadable' => false]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertFalse($vars['timestamped']);
        $this->assertTrue($vars['publishable']);
        $this->assertFalse($vars['uploadable']);
    }

    public function test_generates_with_uploadable(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => true]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertFalse($vars['timestamped']);
        $this->assertFalse($vars['publishable']);
        $this->assertTrue($vars['uploadable']);
    }

    public function test_generates_with_all_annotations(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => true, '--publishable' => true, '--uploadable' => true]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertTrue($vars['timestamped']);
        $this->assertTrue($vars['publishable']);
        $this->assertTrue($vars['uploadable']);
    }

    public function test_output_includes_migration_reminder(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('make:migration', $text);
        $this->assertStringContainsString('review', $text);
    }

    public function test_interact_asks_all_three_questions(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "yes\nno\nno\n");
        rewind($stream);

        $input = new ArrayInput(['name' => 'MyComponent'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $io = new ConsoleStyle($input, $output);

        $this->makeMaker()->interact($input, $io, $command);

        $this->assertTrue($input->getOption('timestamped'));
        $this->assertFalse($input->getOption('publishable'));
        $this->assertFalse($input->getOption('uploadable'));

        fclose($stream);
    }
}
