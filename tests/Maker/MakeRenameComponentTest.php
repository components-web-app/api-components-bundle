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
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeRenameComponentTest extends TestCase
{
    private const BLANK_NODE = '/.well-known/genid/956539f238167984af66';

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
        $input = new ArrayInput([]);
        $input->setInteractive(false);

        return new ConsoleStyle($input, $output ?? new BufferedOutput());
    }

    private function interactiveInput(Command $command, array $params, string $answers): ArrayInput
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $answers);
        rewind($stream);

        $input = new ArrayInput($params, $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        return $input;
    }

    private function fullyQualifiedParams(): array
    {
        return [
            'old-name' => 'HtmlContent',
            'new-name' => 'RichText',
            '--old-fqcn' => 'App\\Entity\\Component\\HtmlContent',
            '--new-fqcn' => 'App\\Entity\\Component\\RichText',
            '--old-dtype' => 'htmlcontent',
            '--new-dtype' => 'richtext',
        ];
    }

    private function iriConverterByClass(string $oldIri, string $newIri): IriConverterInterface
    {
        $mock = $this->createStub(IriConverterInterface::class);
        $mock->method('getIriFromResource')
            ->willReturnCallback(static fn (string $class): string => str_ends_with($class, 'HtmlContent') ? $oldIri : $newIri);

        return $mock;
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
        return $this->registryWithGroups([]);
    }

    private function registryWithGroups(array $groups): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findAll')->willReturn($groups);
        $registry->method('getRepository')->with(ComponentGroup::class)->willReturn($repo);

        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturnCallback(static function (string $class): ClassMetadata {
            $metadata = new ClassMetadata($class);
            $metadata->setPrimaryTable(['name' => '_acb_' . (ComponentGroup::class === $class ? 'component_group' : 'abstract_component')]);

            return $metadata;
        });
        $registry->method('getManagerForClass')->willReturn($manager);

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
        fwrite($stream, "App\\Entity\\Component\\HtmlContent\ncustom_html\nApp\\Entity\\Component\\RichText\ncustom_rich\n");
        rewind($stream);

        $input = new ArrayInput(['old-name' => 'HtmlContent', 'new-name' => 'RichText'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->makeMaker($this->iriConverterByClass('/component/html_contents', '/component/rich_texts'))->interact($input, new ConsoleStyle($input, $output), $command);

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

    public function test_generate_refuses_a_blank_node_old_iri_and_names_the_option(): void
    {
        $generator = new RenderingMigrationGenerator();

        try {
            $this->makeMaker($this->iriConverterByClass(self::BLANK_NODE, '/component/rich_texts'), $this->emptyRegistry())
                ->generate($this->defaultInput(), $this->makeIo(), $generator);
            $this->fail('Expected the maker to refuse an unresolvable old IRI.');
        } catch (RuntimeCommandException $e) {
            $this->assertStringContainsString('--old-iri', $e->getMessage());
            $this->assertStringContainsString('App\\Entity\\Component\\HtmlContent', $e->getMessage());
        }

        $this->assertFalse($generator->hasGenerated());
    }

    public function test_generate_refuses_a_blank_node_new_iri_and_names_the_option(): void
    {
        $generator = new RenderingMigrationGenerator();

        try {
            $this->makeMaker($this->iriConverterByClass('/component/html_contents', self::BLANK_NODE), $this->emptyRegistry())
                ->generate($this->defaultInput(), $this->makeIo(), $generator);
            $this->fail('Expected the maker to refuse an unresolvable new IRI.');
        } catch (RuntimeCommandException $e) {
            $this->assertStringContainsString('--new-iri', $e->getMessage());
            $this->assertStringNotContainsString('--old-iri', $e->getMessage());
        }

        $this->assertFalse($generator->hasGenerated());
    }

    public function test_generate_refuses_when_the_iri_converter_throws(): void
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willThrowException(new \RuntimeException('Not a resource'));

        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('--old-iri');

        $this->makeMaker($iriConverter, $this->emptyRegistry())
            ->generate($this->defaultInput(), $this->makeIo(), new RenderingMigrationGenerator());
    }

    public function test_generate_uses_the_iri_options_for_unresolvable_classes(): void
    {
        $generator = new RenderingMigrationGenerator();
        $this->makeMaker($this->iriConverterByClass(self::BLANK_NODE, self::BLANK_NODE), $this->emptyRegistry())
            ->generate($this->boundInput($this->fullyQualifiedParams() + ['--old-iri' => '/component/html_contents', '--new-iri' => '/component/rich_texts']), $this->makeIo(), $generator);

        $this->assertSame('/component/html_contents', $generator->getVariables()['old_iri']);
        $this->assertSame('/component/rich_texts', $generator->getVariables()['new_iri']);
    }

    public function test_generate_never_writes_a_blank_node_into_the_migration(): void
    {
        $generator = new RenderingMigrationGenerator();

        try {
            $this->makeMaker($this->iriConverterByClass('/component/html_contents', self::BLANK_NODE), $this->emptyRegistry())
                ->generate($this->defaultInput(), $this->makeIo(), $generator);
        } catch (RuntimeCommandException) {
        }

        $this->assertFalse($generator->hasGenerated() && str_contains($generator->getSource(), '/.well-known/genid/'));
    }

    public function test_generate_rejects_a_blank_node_passed_as_an_iri_option(): void
    {
        $generator = new RenderingMigrationGenerator();

        try {
            $this->makeMaker($this->iriConverterByClass('/component/html_contents', self::BLANK_NODE), $this->emptyRegistry())
                ->generate($this->boundInput($this->fullyQualifiedParams() + ['--new-iri' => self::BLANK_NODE]), $this->makeIo(), $generator);
            $this->fail('Expected the maker to reject a blank node IRI option.');
        } catch (RuntimeCommandException $e) {
            $this->assertStringContainsString('--new-iri', $e->getMessage());
        }

        $this->assertFalse($generator->hasGenerated());
    }

    public function test_generate_rejects_an_iri_option_that_is_not_a_path(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('--old-iri');

        $this->makeMaker($this->iriConverterByClass('/component/html_contents', '/component/rich_texts'), $this->emptyRegistry())
            ->generate($this->boundInput($this->fullyQualifiedParams() + ['--old-iri' => 'html_contents']), $this->makeIo(), new RenderingMigrationGenerator());
    }

    public function test_generate_prefers_an_explicit_iri_option_over_the_resolved_iri(): void
    {
        $generator = new RenderingMigrationGenerator();
        $this->makeMaker($this->iriConverterByClass('/component/html_contents', '/component/rich_texts'), $this->emptyRegistry())
            ->generate($this->boundInput($this->fullyQualifiedParams() + ['--old-iri' => '/component/legacy_html']), $this->makeIo(), $generator);

        $this->assertSame('/component/legacy_html', $generator->getVariables()['old_iri']);
        $this->assertSame('/component/rich_texts', $generator->getVariables()['new_iri']);
    }

    public function test_interact_prompts_only_for_the_iri_that_cannot_be_resolved(): void
    {
        $command = $this->configuredCommand();
        $input = $this->interactiveInput($command, $this->fullyQualifiedParams(), "/component/rich_texts\n");
        $output = new BufferedOutput();

        $this->makeMaker($this->iriConverterByClass('/component/html_contents', self::BLANK_NODE))
            ->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame('/component/rich_texts', $input->getOption('new-iri'));
        $this->assertNull($input->getOption('old-iri'));
        $text = $output->fetch();
        $this->assertStringContainsString('NEW component', $text);
        $this->assertStringNotContainsString('OLD component', $text);
        $this->assertStringContainsString('App\\Entity\\Component\\RichText', $text);
    }

    public function test_interact_asks_again_when_the_answer_is_a_blank_node(): void
    {
        $command = $this->configuredCommand();
        $input = $this->interactiveInput($command, $this->fullyQualifiedParams(), self::BLANK_NODE . "\n/component/html_contents\n");

        $this->makeMaker($this->iriConverterByClass(self::BLANK_NODE, '/component/rich_texts'))
            ->interact($input, new ConsoleStyle($input, new BufferedOutput()), $command);

        $this->assertSame('/component/html_contents', $input->getOption('old-iri'));
        $this->assertNull($input->getOption('new-iri'));
    }

    public function test_interact_does_not_prompt_for_iris_when_both_classes_resolve(): void
    {
        $command = $this->configuredCommand();
        $input = $this->interactiveInput($command, $this->fullyQualifiedParams(), '');
        $output = new BufferedOutput();

        $this->makeMaker($this->iriConverterByClass('/component/html_contents', '/component/rich_texts'))
            ->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertNull($input->getOption('old-iri'));
        $this->assertNull($input->getOption('new-iri'));
        $this->assertStringNotContainsString('Collection IRI', $output->fetch());
    }

    public function test_interact_does_not_prompt_when_the_iri_option_is_given(): void
    {
        $command = $this->configuredCommand();
        $input = $this->interactiveInput($command, $this->fullyQualifiedParams() + ['--new-iri' => '/component/rich_texts'], '');

        $this->makeMaker($this->iriConverterByClass('/component/html_contents', self::BLANK_NODE))
            ->interact($input, new ConsoleStyle($input, new BufferedOutput()), $command);

        $this->assertSame('/component/rich_texts', $input->getOption('new-iri'));
    }

    public function test_generate_passes_the_orm_mapped_table_names_to_template(): void
    {
        $vars = [];
        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $this->emptyRegistry(),
        )->generate($this->defaultInput(), $this->makeIo(), $this->makeGenerator($vars));

        $this->assertSame('_acb_abstract_component', $vars['component_table']);
        $this->assertSame('_acb_component_group', $vars['group_table']);
    }

    public function test_generate_refuses_when_the_component_tables_are_not_mapped(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $generator = $this->createStub(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Migrations\\VersionRename', 'Migrations\\'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(AbstractComponent::class);

        $this->makeMaker(
            $this->iriConverterReturning('/component/html-content', '/component/rich-text'),
            $registry,
        )->generate($this->defaultInput(), $this->makeIo(), $generator);
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

        $registry = $this->registryWithGroups([$group]);

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

        $registry = $this->registryWithGroups([$group]);

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
