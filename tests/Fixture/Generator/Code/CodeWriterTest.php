<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Code;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\CodeWriter;

class CodeWriterTest extends TestCase
{
    public function test_lines_are_indented_four_spaces_per_level(): void
    {
        $writer = (new CodeWriter())
            ->line('class A')
            ->line('{')
            ->indent()
            ->line('public function b(): void')
            ->line('{')
            ->indent()
            ->line('$c = 1;')
            ->outdent()
            ->line('}')
            ->outdent()
            ->line('}');

        self::assertSame("class A\n{\n    public function b(): void\n    {\n        \$c = 1;\n    }\n}\n", (string) $writer);
    }

    public function test_a_blank_line_has_no_indentation(): void
    {
        $writer = (new CodeWriter())->indent()->line('$a = 1;')->blank()->line('$b = 2;');

        self::assertSame("    \$a = 1;\n\n    \$b = 2;\n", (string) $writer);
    }

    public function test_every_line_of_a_multi_line_string_is_indented_and_empty_lines_stay_empty(): void
    {
        $writer = (new CodeWriter())->indent()->indent()->line("\$a->html = <<<'HTML'\n    <pre>one\n\n    two</pre>\n    HTML;");

        self::assertSame("        \$a->html = <<<'HTML'\n            <pre>one\n\n            two</pre>\n            HTML;\n", (string) $writer);
    }

    public function test_a_chain_goes_one_call_per_line(): void
    {
        $writer = (new CodeWriter())->indent()->chain("\$cwa->layout('main', 'Primary')", ["group('header')", 'add($homeLink)', 'add($aboutLink)']);

        self::assertSame("    \$cwa->layout('main', 'Primary')\n        ->group('header')\n        ->add(\$homeLink)\n        ->add(\$aboutLink);\n", (string) $writer);
    }

    public function test_a_chain_of_one_call_puts_the_call_on_its_own_line(): void
    {
        $writer = (new CodeWriter())->indent()->chain("\$page->group('hero')", ['add($homeHero)']);

        self::assertSame("    \$page->group('hero')\n        ->add(\$homeHero);\n", (string) $writer);
    }

    public function test_a_chain_of_no_calls_is_a_statement(): void
    {
        self::assertSame("\$cwa->redirect('/old', to: 'about');\n", (string) (new CodeWriter())->chain("\$cwa->redirect('/old', to: 'about')", []));
    }

    public function test_a_call_that_fits_in_120_columns_stays_on_one_line(): void
    {
        $writer = (new CodeWriter())->indent()->indent();
        $argument = "'" . str_repeat('a', 112 - \strlen("\$cwa->publish('')")) . "'";
        $call = $writer->call('$cwa->publish', [$argument]);

        self::assertSame(112, \strlen($call));
        self::assertSame('$cwa->publish(' . $argument . ')', $call);
    }

    public function test_a_call_past_120_columns_has_one_argument_per_line_with_a_trailing_comma(): void
    {
        $writer = (new CodeWriter())->indent()->indent();
        $variable = '$' . str_repeat('a', 113 - \strlen("\$cwa->publish(, at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'))") - 1);
        $call = $writer->call('$cwa->publish', [$variable, "at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00')"]);

        self::assertSame("\$cwa->publish(\n    {$variable},\n    at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'),\n)", $call);
    }

    public function test_the_120_column_limit_counts_the_current_indentation(): void
    {
        $arguments = ['$' . str_repeat('a', 50), '$' . str_repeat('b', 50)];
        $call = (new CodeWriter())->call('$cwa->publish', $arguments);

        self::assertStringNotContainsString("\n", $call);
        self::assertStringContainsString("\n", (new CodeWriter())->indent()->indent()->indent()->call('$cwa->publish', $arguments));
    }

    public function test_a_broken_call_is_indented_with_the_statement(): void
    {
        $writer = (new CodeWriter())->indent()->indent();
        $call = $writer->call('$cwa->publish', ['$' . str_repeat('a', 70), "at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00')"]);
        $writer->line($call . ';');

        self::assertSame("        \$cwa->publish(\n            \$" . str_repeat('a', 70) . ",\n            at: new \\DateTimeImmutable('2099-01-15T09:00:00+00:00'),\n        );\n", (string) $writer);
    }
}
