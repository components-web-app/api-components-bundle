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
        // If mutated to $properties, implode would produce 'Array' instead of property names.
        $vars = [];
        $generator = $this->makeGenerator('App\\Entity\\PageData\\ConferenceData', $vars);
        $input = $this->boundInput(['name' => 'ConferenceData', '--properties' => ['headline:?string', 'body:string']]);
        $output = new BufferedOutput();

        $this->makeMaker()->generate($input, $this->makeIo($output), $generator);

        $text = $output->fetch();
        $this->assertStringContainsString("'headline'", $text);
        $this->assertStringContainsString("'body'", $text);
        $this->assertStringNotContainsString('Array', $text);
        // Ensure leading quote is present — mutations like $p."'" produce "headline'" not "'headline'"
        $this->assertMatchesRegularExpression("/'\w+'/", $text);
    }
}
