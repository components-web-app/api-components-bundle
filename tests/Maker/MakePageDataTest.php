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
use Silverback\ApiComponentsBundle\Maker\MakePageData;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakePageDataTest extends TestCase
{
    private function makeMaker(): MakePageData
    {
        return new MakePageData();
    }

    private function configuredCommand(): Command
    {
        $maker = $this->makeMaker();
        $command = new Command('make:page-data');
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

    /** Normalises console output to \n line endings with trailing whitespace stripped. */
    private function normalize(string $text): string
    {
        return implode("\n", array_map('rtrim', explode(\PHP_EOL, $text)));
    }

    private function makeGenerator(string $expectedClass, array &$capturedVars): Generator
    {
        $generator = $this->createMock(Generator::class);

        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails($expectedClass, 'Entity\\PageData\\'));

        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/PageData/' . basename($className) . '.php';
            });

        $generator->expects($this->once())->method('writeChanges');

        return $generator;
    }

    public function test_command_name(): void
    {
        $this->assertSame('make:page-data', MakePageData::getCommandName());
    }

    public function test_command_description(): void
    {
        $this->assertNotEmpty(MakePageData::getCommandDescription());
    }

    public function test_generates_minimal_page_data_with_no_properties(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertSame([], $vars['properties']);
    }

    public function test_generates_with_nullable_string_property(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(1, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('?string', $vars['properties'][0]['type']);
        $this->assertTrue($vars['properties'][0]['nullable']);
    }

    public function test_generates_with_non_nullable_property(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['title:string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(1, $vars['properties']);
        $this->assertSame('title', $vars['properties'][0]['name']);
        $this->assertSame('string', $vars['properties'][0]['type']);
        $this->assertFalse($vars['properties'][0]['nullable']);
    }

    public function test_generates_with_multiple_properties(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string', 'body:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(2, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('body', $vars['properties'][1]['name']);
    }

    public function test_stdout_contains_nuxt_config_properties_block(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string', 'body:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('nuxt.config', $text);
        $this->assertStringContainsString('cwa.pageData', $text);
        $this->assertStringContainsString('headline', $text);
        $this->assertStringContainsString('body', $text);
        $this->assertStringContainsString('properties', $text);
    }

    public function test_stdout_contains_fixture_stub(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('->pageData(', $text);
        $this->assertStringContainsString('->pageDataPosition(', $text);
        $this->assertStringContainsString('ConferenceData', $text);
        $this->assertStringContainsString('headline', $text);
    }

    public function test_stdout_does_not_contain_page_data_position_when_no_properties(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('->pageData(', $text);
        $this->assertStringNotContainsString('->pageDataPosition(', $text);
    }

    public function test_output_includes_migration_reminder(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('make:migration', $text);
    }

    public function test_template_path_points_to_page_data_skeleton(): void
    {
        $capturedTemplate = null;
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\PageData\\ConferenceData', 'Entity\\PageData\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedTemplate): string {
                $capturedTemplate = $template;

                return 'src/Entity/PageData/ConferenceData.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString('page_data', $capturedTemplate);
        $this->assertStringContainsString('PageData.tpl.php', $capturedTemplate);
        $this->assertStringStartsWith('/', $capturedTemplate);
    }

    public function test_nuxt_config_output_contains_class_short_name_as_key(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        // Must contain "ConferenceData: {" as a nuxt.config key (ConsoleStyle strips tag formatting)
        $this->assertMatchesRegularExpression('/ConferenceData\s*:\s*\{/i', $output->fetch());
    }

    public function test_property_type_with_colon_is_preserved_as_full_type(): void
    {
        // explode(':', $raw, 2) ensures the type portion keeps any colons it contains.
        // If the limit were changed to 3, a type like "?Acme\\Type:extra" would be truncated.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['field:?string:ignored']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertSame('?string:ignored', $vars['properties'][0]['type']);
    }

    public function test_template_path_is_real_existing_file(): void
    {
        $capturedTemplate = null;
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\PageData\\ConferenceData', 'Entity\\PageData\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedTemplate): string {
                $capturedTemplate = $template;

                return 'src/Entity/PageData/ConferenceData.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'ConferenceData']);
        $this->makeMaker()->generate($input, $this->makeIo(new BufferedOutput()), $generator);

        $this->assertFileExists($capturedTemplate);
        $this->assertStringEndsWith('PageData.tpl.php', $capturedTemplate);
    }

    public function test_use_statements_include_api_resource(): void
    {
        $capturedVars = [];
        $generator = $this->createMock(Generator::class);
        $generator->method('createClassNameDetails')
            ->willReturn(new ClassNameDetails('App\\Entity\\PageData\\ConferenceData', 'Entity\\PageData\\'));
        $generator->expects($this->once())
            ->method('generateClass')
            ->willReturnCallback(static function (string $className, string $template, array $vars) use (&$capturedVars): string {
                $capturedVars = $vars;

                return 'src/Entity/PageData/ConferenceData.php';
            });
        $generator->method('writeChanges');

        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();
        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertArrayHasKey('use_statements', $capturedVars);
        $useStatementsStr = (string) $capturedVars['use_statements'];
        $this->assertStringContainsString('ApiResource', $useStatementsStr);
        $this->assertStringContainsString('ORM', $useStatementsStr);
    }

    public function test_generate_outputs_success_message(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString('success', strtolower($output->fetch()));
    }

    public function test_nuxt_config_output_contains_property_names_not_raw_arrays(): void
    {
        // array_column($properties, 'name') must extract names — not pass full property arrays.
        // If mutated to $properties, the map keys would render as 'Array'.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string', 'body:string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString("headline: 'Headline',", $text);
        $this->assertStringContainsString("body: 'Body',", $text);
        $this->assertStringNotContainsString('Array', $text);
        // Ensure the label quotes are present — mutations like $p."'" produce "Headline'" not "'Headline'"
        $this->assertMatchesRegularExpression("/'\w+'/", $text);
    }

    // ---------------------------------------------------------------------
    // #212 — the nuxt.config snippet must be a name => label map, not an array
    // ---------------------------------------------------------------------

    public function test_nuxt_config_properties_is_a_label_map_not_an_array(): void
    {
        // The module's config type is `properties?: { [propertyName: string]: string }`
        // (cwa-nuxt-3-module src/runtime/types/index.ts). An array is invalid there.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string,body:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('properties: {', $text);
        $this->assertStringNotContainsString('properties: [', $text);
        $this->assertStringContainsString("headline: 'Headline',", $text);
        $this->assertStringContainsString("body: 'Body',", $text);
        $this->assertStringContainsString('},', $text);
    }

    public function test_nuxt_config_snippet_is_rendered_verbatim(): void
    {
        // Pins the whole pasteable block — indentation, key order, braces and trailing
        // commas — so an accidental change to any one line is caught.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string,heroImage:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $expected = implode("\n", [
            ' Add the following to your nuxt.config.ts under cwa.pageData:',
            '',
            '   ConferenceData: {',
            "     name: 'Conference Data',",
            '     properties: {',
            "       headline: 'Headline',",
            "       heroImage: 'Hero Image',",
            '     },',
            '   },',
            '',
            ' Fixture scaffold stub:',
            '',
            "   \$cwa->pageData(new ConferenceData(), template: 'my-template');",
        ]);

        $this->assertStringContainsString($expected, $this->normalize($output->fetch()));
    }

    public function test_nuxt_config_snippet_without_properties_is_rendered_verbatim(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $expected = implode("\n", [
            '   ConferenceData: {',
            "     name: 'Conference Data',",
            '     properties: {},',
            '   },',
        ]);

        $this->assertStringContainsString($expected, $this->normalize($output->fetch()));
    }

    public function test_nuxt_config_labels_are_humanized_from_camel_case(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['heroImage:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString("heroImage: 'Hero Image',", $output->fetch());
    }

    public function test_nuxt_config_labels_are_humanized_from_snake_case(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['hero_image:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString("hero_image: 'Hero Image',", $output->fetch());
    }

    public function test_nuxt_config_labels_ignore_leading_and_trailing_separators(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['_heroImage_:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString("_heroImage_: 'Hero Image',", $output->fetch());
    }

    public function test_nuxt_config_includes_a_humanized_name_for_the_entity(): void
    {
        // `name` is read by the module (useDataType → pageDataClassName) and is part of the
        // same pageData config entry, so the snippet should be complete.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString("name: 'Conference Data',", $output->fetch());
    }

    public function test_nuxt_config_renders_an_empty_properties_map_when_there_are_none(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString('properties: {},', $text);
        $this->assertStringNotContainsString('properties: [', $text);
        $this->assertStringContainsString("name: 'Conference Data',", $text);
    }

    // ---------------------------------------------------------------------
    // #211 — interact() + a --properties option that survives a one-liner
    // ---------------------------------------------------------------------

    public function test_properties_accepts_a_single_comma_separated_list(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string,body:string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(2, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('?string', $vars['properties'][0]['type']);
        $this->assertTrue($vars['properties'][0]['nullable']);
        $this->assertSame('body', $vars['properties'][1]['name']);
        $this->assertSame('string', $vars['properties'][1]['type']);
        $this->assertFalse($vars['properties'][1]['nullable']);
    }

    public function test_properties_entries_are_trimmed(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => [' headline : ?string , body : string ']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(2, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('?string', $vars['properties'][0]['type']);
        $this->assertSame('body', $vars['properties'][1]['name']);
        $this->assertSame('string', $vars['properties'][1]['type']);
    }

    public function test_empty_property_entries_are_skipped(): void
    {
        // The empty entries deliberately surround a real one: skipping must `continue`,
        // not `break`, or everything after the first blank would be dropped.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => [',headline:?string,, ,body:string,', '  ']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(2, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('body', $vars['properties'][1]['name']);
    }

    public function test_property_without_a_type_defaults_to_nullable_string(): void
    {
        // Previously `explode(':', $raw, 2)` on a value with no colon emitted an
        // "Undefined array key 1" error and produced a property with a null type.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertCount(1, $vars['properties']);
        $this->assertSame('headline', $vars['properties'][0]['name']);
        $this->assertSame('?string', $vars['properties'][0]['type']);
        $this->assertTrue($vars['properties'][0]['nullable']);
    }

    public function test_property_with_an_empty_type_defaults_to_nullable_string(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertSame('?string', $vars['properties'][0]['type']);
    }

    public function test_property_with_no_name_is_rejected_with_a_helpful_message(): void
    {
        $generator = $this->createMock(Generator::class);
        $generator->expects($this->never())->method('generateClass');
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => [':?string']]);

        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('Invalid property definition ":?string"');

        $this->makeMaker()->generate($input, $this->makeIo(new BufferedOutput()), $generator);
    }

    public function test_properties_option_is_an_array_and_requires_a_value(): void
    {
        // VALUE_REQUIRED means a bare `--properties` errors clearly instead of injecting null.
        $option = $this->configuredCommand()->getDefinition()->getOption('properties');

        $this->assertTrue($option->isArray());
        $this->assertTrue($option->isValueRequired());
        $this->assertSame([], $option->getDefault());
        $this->assertSame(
            'Property definitions in <comment>name:type</comment> format. Repeat the flag, or pass one comma-separated list. Omitting <comment>:type</comment> defaults to <comment>?string</comment>',
            $option->getDescription()
        );
    }

    public function test_command_help_documents_both_working_forms_and_the_space_separated_trap(): void
    {
        $help = $this->configuredCommand()->getHelp();

        $this->assertStringContainsString('headline:?string,body:?string', $help);
        $this->assertStringContainsString('--properties headline:?string --properties body:?string', $help);
        $this->assertStringContainsString('Too many arguments', $help);
    }

    public function test_generate_warns_when_no_properties_were_defined(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData']);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringContainsString('No properties were defined', $output->fetch());
    }

    public function test_generate_does_not_warn_when_properties_were_defined(): void
    {
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $this->assertStringNotContainsString('No properties were defined', $output->fetch());
    }

    public function test_interact_prompts_for_properties_until_a_blank_name(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "headline\n?string\nbody\nstring\n\n");
        rewind($stream);

        $input = new ArrayInput(['name' => 'ConferenceData'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->makeMaker()->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame(['headline:?string', 'body:string'], $input->getOption('properties'));
        $this->assertStringContainsString('Add the properties', $output->fetch());

        fclose($stream);
    }

    public function test_interact_property_type_defaults_to_nullable_string_when_blank(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "headline\n\n\n");
        rewind($stream);

        $input = new ArrayInput(['name' => 'ConferenceData'], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->makeMaker()->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame(['headline:?string'], $input->getOption('properties'));

        fclose($stream);
    }

    public function test_interact_does_not_prompt_when_properties_are_already_given(): void
    {
        $command = $this->configuredCommand();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "shouldNotBeRead\n?string\n\n");
        rewind($stream);

        $input = new ArrayInput(['name' => 'ConferenceData', '--properties' => ['headline:?string']], $command->getDefinition());
        $input->setStream($stream);
        $input->setInteractive(true);

        $output = new BufferedOutput();
        $this->makeMaker()->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame(['headline:?string'], $input->getOption('properties'));
        $this->assertSame('', $output->fetch());

        fclose($stream);
    }

    public function test_interact_leaves_properties_empty_in_non_interactive_mode(): void
    {
        $command = $this->configuredCommand();

        $input = new ArrayInput(['name' => 'ConferenceData'], $command->getDefinition());
        $input->setInteractive(false);

        $output = new BufferedOutput();
        $this->makeMaker()->interact($input, new ConsoleStyle($input, $output), $command);

        $this->assertSame([], $input->getOption('properties'));
    }
}
