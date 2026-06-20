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

    public function test_interact_sets_publishable_and_uploadable_from_stream(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "no\nyes\nyes\n");
        rewind($stream);

        $input = new ArrayInput(['name' => 'MyComponent'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $io = new ConsoleStyle($input, $output);

        $this->makeMaker()->interact($input, $io, $command);

        $this->assertFalse($input->getOption('timestamped'));
        $this->assertTrue($input->getOption('publishable'));
        $this->assertTrue($input->getOption('uploadable'));

        fclose($stream);
    }

    public function test_interact_does_not_override_options_already_set_to_true(): void
    {
        // If the logical-NOT guard is mutated to if($option) instead of if(!$option),
        // it would re-ask the question and potentially overwrite the pre-set value.
        // With non-interactive mode, confirm() returns the default (false), so the
        // option would become false — killing the mutant.
        $command = $this->configuredCommand();

        $input = new ArrayInput(
            ['name' => 'MyComponent', '--timestamped' => true, '--publishable' => true, '--uploadable' => true],
            $command->getDefinition()
        );
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $io = new ConsoleStyle($input, $output);

        $this->makeMaker()->interact($input, $io, $command);

        $this->assertTrue($input->getOption('timestamped'));
        $this->assertTrue($input->getOption('publishable'));
        $this->assertTrue($input->getOption('uploadable'));
    }

    public function test_interact_defaults_all_options_to_false_in_non_interactive_mode(): void
    {
        // Kills FalseValue mutations: if the default were changed from false to true,
        // non-interactive mode would return true (the default) for unanswered confirms,
        // causing setOption to set them to true. This test asserts false.
        $command = $this->configuredCommand();

        $input = new ArrayInput(['name' => 'MyComponent'], $command->getDefinition());
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $io = new ConsoleStyle($input, $output);

        $this->makeMaker()->interact($input, $io, $command);

        $this->assertFalse($input->getOption('timestamped'));
        $this->assertFalse($input->getOption('publishable'));
        $this->assertFalse($input->getOption('uploadable'));
    }

    public function test_use_statements_include_all_base_namespaces(): void
    {
        // Kills ArrayItemRemoval on UseStatementGenerator array (lines 75-79):
        // removing ApiResource, ORM, Silverback, or AbstractComponent would
        // make the use_statements string not contain those identifiers.
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertArrayHasKey('use_statements', $capturedVars);
        $useStr = (string) $capturedVars['use_statements'];
        $this->assertStringContainsString('ApiResource', $useStr);
        $this->assertStringContainsString('ORM', $useStr);
        $this->assertStringContainsString('Silverback', $useStr);
        $this->assertStringContainsString('AbstractComponent', $useStr);
    }

    public function test_use_statements_include_timestamped_trait_when_timestamped_is_true(): void
    {
        // Kills IfNegation (if($timestamped) → if(!$timestamped)) and
        // MethodCallRemoval on addUseStatement(TimestampedTrait::class).
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => true, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $useStr = (string) $capturedVars['use_statements'];
        $this->assertStringContainsString('TimestampedTrait', $useStr);
        $this->assertStringNotContainsString('PublishableTrait', $useStr);
        $this->assertStringNotContainsString('UploadableTrait', $useStr);
    }

    public function test_use_statements_include_publishable_trait_when_publishable_is_true(): void
    {
        // Kills IfNegation on if($publishable) and MethodCallRemoval on addUseStatement(PublishableTrait::class).
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => true, '--uploadable' => false]);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $useStr = (string) $capturedVars['use_statements'];
        $this->assertStringNotContainsString('TimestampedTrait', $useStr);
        $this->assertStringContainsString('PublishableTrait', $useStr);
        $this->assertStringNotContainsString('UploadableTrait', $useStr);
    }

    public function test_use_statements_include_uploadable_trait_and_file_when_uploadable_is_true(): void
    {
        // Kills IfNegation on if($uploadable) and MethodCallRemoval on both
        // addUseStatement(UploadableTrait::class) and addUseStatement(File::class).
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => true]);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $useStr = (string) $capturedVars['use_statements'];
        $this->assertStringNotContainsString('TimestampedTrait', $useStr);
        $this->assertStringNotContainsString('PublishableTrait', $useStr);
        $this->assertStringContainsString('UploadableTrait', $useStr);
        $this->assertStringContainsString('File', $useStr);
    }

    public function test_use_statements_do_not_include_trait_use_statements_when_all_flags_false(): void
    {
        // Kills IfNegation mutations: with if(!$timestamped) mutant, the Timestamped trait
        // would be added when timestamped=false, making this assertion fail.
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $useStr = (string) $capturedVars['use_statements'];
        $this->assertStringNotContainsString('TimestampedTrait', $useStr);
        $this->assertStringNotContainsString('PublishableTrait', $useStr);
        $this->assertStringNotContainsString('UploadableTrait', $useStr);
    }

    public function test_generate_outputs_success_message(): void
    {
        // Kills MethodCallRemoval on writeSuccessMessage (line 106).
        // AbstractMaker::writeSuccessMessage() writes a success block with the word "success".
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\Component\\MyComponent', $vars);
        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('success', strtolower($text));
    }

    public function test_generate_template_path_points_to_component_skeleton(): void
    {
        // Kills ConcatOperandRemoval/Concat mutations at line 95 — ensures the path contains
        // both the skeleton directory fragment and the filename.
        $capturedTemplate = null;
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\Component\\MyComponent', 'Entity\\Component\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedTemplate): string {
                $capturedTemplate = $template;

                return 'src/Entity/Component/MyComponent.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'MyComponent', '--timestamped' => false, '--publishable' => false, '--uploadable' => false]);
        $this->makeMaker()->generate($input, $this->makeIo(new BufferedOutput()), $generator);

        $this->assertStringContainsString('component', $capturedTemplate);
        $this->assertStringContainsString('Component.tpl.php', $capturedTemplate);
        $this->assertStringStartsWith('/', $capturedTemplate);
        $this->assertFileExists($capturedTemplate);
    }
}
