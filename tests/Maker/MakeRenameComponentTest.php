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

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeRenameComponentTest extends TestCase
{
    private function makeMaker(
        ?IriConverterInterface $iriConverter = null,
        ?ManagerRegistry $registry = null,
    ): MakeRenameComponent {
        return new MakeRenameComponent(
            $iriConverter ?? $this->createMock(IriConverterInterface::class),
            $registry ?? $this->createMock(ManagerRegistry::class),
        );
    }

    private function configuredCommand(?MakeRenameComponent $maker = null): Command
    {
        $maker ??= $this->makeMaker();
        $command = new Command('make:rename-component');
        $maker->configureCommand($command, new InputConfiguration());

        return $command;
    }

    private function boundInput(array $params): ArrayInput
    {
        $input = new ArrayInput($params, $this->configuredCommand()->getDefinition());
        $input->setInteractive(false);

        return $input;
    }

    private function makeIo(?BufferedOutput $output = null): ConsoleStyle
    {
        return new ConsoleStyle(new ArrayInput([]), $output ?? new BufferedOutput());
    }

    private function makeGenerator(array &$capturedVars): Generator
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Migrations\\VersionRename', 'Migrations\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $class, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'migrations/VersionRename.php';
            });
        $generator->expects($this->once())->method('writeChanges');

        return $generator;
    }

    private function emptyRegistry(): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findAll')->willReturn([]);
        $registry->method('getRepository')->with(ComponentGroup::class)->willReturn($repo);

        return $registry;
    }

    private function defaultInput(): ArrayInput
    {
        return $this->boundInput([
            'old-name' => 'HtmlContent',
            'new-name' => 'RichText',
            '--old-fqcn' => 'App\\Entity\\Component\\HtmlContent',
            '--new-fqcn' => 'App\\Entity\\Component\\RichText',
            '--old-dtype' => 'htmlcontent',
            '--new-dtype' => 'richtext',
        ]);
    }

    private function iriConverterReturning(string $oldIri, string $newIri): IriConverterInterface
    {
        $mock = $this->createMock(IriConverterInterface::class);
        $mock->method('getIriFromResource')
            ->willReturnOnConsecutiveCalls($oldIri, $newIri);

        return $mock;
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:rename-component', MakeRenameComponent::getCommandName());
    }

    public function test_command_description(): void
    {
        $this->assertNotEmpty(MakeRenameComponent::getCommandDescription());
    }

    public function test_configures_old_name_argument(): void
    {
        $this->assertTrue($this->configuredCommand()->getDefinition()->hasArgument('old-name'));
    }

    public function test_configures_new_name_argument(): void
    {
        $this->assertTrue($this->configuredCommand()->getDefinition()->hasArgument('new-name'));
    }

    public function test_interact_sets_default_old_fqcn_from_class_name(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('App\\Entity\\Component\\HtmlContent', $input->getOption('old-fqcn'));
    }

    public function test_interact_sets_default_new_fqcn_from_class_name(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('App\\Entity\\Component\\RichText', $input->getOption('new-fqcn'));
    }

    public function test_interact_sets_default_old_dtype_as_lowercased_short_name(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('htmlcontent', $input->getOption('old-dtype'));
    }

    public function test_interact_sets_default_new_dtype_as_lowercased_short_name(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('richtext', $input->getOption('new-dtype'));
    }

    public function test_interact_does_not_override_preset_old_dtype(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(
            ['old-name' => 'HtmlContent', 'new-name' => 'RichText', '--old-dtype' => 'custom_dtype'],
            $command->getDefinition()
        );
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('custom_dtype', $input->getOption('old-dtype'));
    }

    public function test_interact_does_not_override_preset_new_dtype(): void
    {
        $command = $this->configuredCommand();
        $input = new ArrayInput(
            ['old-name' => 'HtmlContent', 'new-name' => 'RichText', '--new-dtype' => 'custom_rich'],
            $command->getDefinition()
        );
        $input->setInteractive(false);

        $this->makeMaker()->interact($input, $this->makeIo(), $command);

        $this->assertSame('custom_rich', $input->getOption('new-dtype'));
    }

    public function test_interact_allows_user_to_override_dtype_interactively(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        // Answers in order: old-fqcn, old-dtype, new-fqcn, new-dtype
        fwrite($stream, "App\\Entity\\Component\\HtmlContent\ncustom_html\nApp\\Entity\\Component\\RichText\ncustom_rich\n");
        rewind($stream);

        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->makeMaker()->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame('custom_html', $input->getOption('old-dtype'));
        $this->assertSame('custom_rich', $input->getOption('new-dtype'));

        fclose($stream);
    }

    public function test_generate_passes_old_and_new_dtype_to_template(): void
    {
        $vars = [];
        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $this->emptyRegistry(),
        )->generate($this->defaultInput(), $this->makeIo(), $this->makeGenerator($vars));

        $this->assertSame('htmlcontent', $vars['old_dtype']);
        $this->assertSame('richtext', $vars['new_dtype']);
    }

    public function test_generate_passes_iris_from_iri_converter_to_template(): void
    {
        $vars = [];
        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $this->emptyRegistry(),
        )->generate($this->defaultInput(), $this->makeIo(), $this->makeGenerator($vars));

        $this->assertSame('/component/html-content', $vars['old_iri']);
        $this->assertSame('/component/rich-text', $vars['new_iri']);
    }

    public function test_generate_falls_back_to_derived_old_iri_when_iri_converter_throws(): void
    {
        $vars = [];
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willThrowException(new \RuntimeException('Class not found'));

        $this->makeMaker($iriConverter, $this->emptyRegistry())
            ->generate($this->defaultInput(), $this->makeIo(), $this->makeGenerator($vars));

        $this->assertSame('/component/html-content', $vars['old_iri']);
    }

    public function test_generate_falls_back_to_derived_new_iri_when_iri_converter_throws(): void
    {
        $vars = [];
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willThrowException(new \RuntimeException('Class not found'));

        $this->makeMaker($iriConverter, $this->emptyRegistry())
            ->generate($this->defaultInput(), $this->makeIo(), $this->makeGenerator($vars));

        $this->assertSame('/component/rich-text', $vars['new_iri']);
    }

    public function test_generate_uses_migration_skeleton_template(): void
    {
        $capturedTemplate = null;
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Migrations\\Version', 'Migrations\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $class, string $template) use (&$capturedTemplate): string {
                $capturedTemplate = $template;

                return 'migrations/Version.php';
            });
        $generator->method('writeChanges');

        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $this->emptyRegistry(),
        )->generate($this->defaultInput(), $this->makeIo(), $generator);

        $this->assertNotNull($capturedTemplate);
        $this->assertStringContainsString('migration', strtolower($capturedTemplate));
        $this->assertStringEndsWith('.tpl.php', $capturedTemplate);
        $this->assertFileExists($capturedTemplate);
    }

    public function test_generate_outputs_warning_per_affected_component_group(): void
    {
        $group = new ComponentGroup();
        $group->location = '/page/abc123';
        $group->reference = 'primary';
        $group->allowedComponents = ['/component/html-content'];

        $registry = $this->createMock(ManagerRegistry::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findAll')->willReturn([$group]);
        $registry->method('getRepository')->with(ComponentGroup::class)->willReturn($repo);

        $output = new BufferedOutput();
        $vars = [];

        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $registry,
        )->generate($this->defaultInput(), $this->makeIo($output), $this->makeGenerator($vars));

        $text = $output->fetch();
        $this->assertStringContainsString('/component/html-content', $text);
        $this->assertStringContainsString('/page/abc123', $text);
        $this->assertStringContainsString('primary', $text);
    }

    public function test_generate_outputs_no_warning_when_group_uses_different_component(): void
    {
        $group = new ComponentGroup();
        $group->location = '/page/abc123';
        $group->reference = 'nav';
        $group->allowedComponents = ['/component/navigation-link'];

        $registry = $this->createMock(ManagerRegistry::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findAll')->willReturn([$group]);
        $registry->method('getRepository')->with(ComponentGroup::class)->willReturn($repo);

        $output = new BufferedOutput();
        $vars = [];

        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $registry,
        )->generate($this->defaultInput(), $this->makeIo($output), $this->makeGenerator($vars));

        $text = $output->fetch();
        $this->assertStringNotContainsString('/page/abc123', $text);
    }

    public function test_generate_outputs_frontend_rename_checklist(): void
    {
        $output = new BufferedOutput();
        $vars = [];

        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $this->emptyRegistry(),
        )->generate($this->defaultInput(), $this->makeIo($output), $this->makeGenerator($vars));

        $text = $output->fetch();
        $this->assertStringContainsString('HtmlContent', $text);
        $this->assertStringContainsString('RichText', $text);
    }
}
