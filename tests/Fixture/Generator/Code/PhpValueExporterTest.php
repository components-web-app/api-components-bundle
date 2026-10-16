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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\ImportCollector;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\PhpValueExporter;

class PhpValueExporterTest extends TestCase
{
    private const string GETTING_STARTED = "<h3>Getting started</h3><p>Install the bundle, then generate a scaffold from your site:</p><pre><code>composer require silverback/api-components-bundle\nphp bin/console silverback:api-components:generate-fixtures --output=src/DataFixtures/SiteScaffold.php</code></pre>";

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function values(): iterable
    {
        yield 'an empty string' => [''];
        yield 'single quotes' => ["it's"];
        yield 'double quotes' => ['say "hi"'];
        yield 'a backslash' => ['a\\b'];
        yield 'an escaped quote' => ["\\'"];
        yield 'a short value with a newline' => ["a\nb"];
        yield 'a long value with a newline' => [self::GETTING_STARTED];
        yield 'CRLF line endings' => [str_repeat("<p>Line ending in CRLF.</p>\r\n", 6)];
        yield 'a trailing newline' => [self::GETTING_STARTED . "\n"];
        yield 'a line equal to the nowdoc marker' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\nHTML\n<p>after</p>"];
        yield 'lines equal to every numbered marker' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\nHTML\nHTML2\n  HTML3\n<p>after</p>"];
        yield 'a line starting with a tab' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n\tindented\n<p>after</p>"];
        yield 'a line starting with spaces' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n    indented\n<p>after</p>"];
        yield 'a blank line' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n\n<p>after</p>"];
        yield 'multibyte characters' => [str_repeat('<p>Café — 東京 🎉</p>', 10)];
        yield 'a NUL byte' => ["a\0b"];
        yield 'a NUL byte in long HTML' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\0<p>after</p>"];
        yield 'upper-case closing tags' => [str_repeat('<P>Paragraph text that is long enough.</P>', 5)];
        yield 'a 5,000 character paragraph' => ['<p>' . str_repeat('a', 5000) . '</p>'];
        yield 'quotes and backslashes in long HTML' => [str_repeat('<p class="x">It\'s a \\path\\ and a "quote".</p>', 5)];
        yield 'every block tag' => ['<h1>a</h1><h2>b</h2><h3>c</h3><h4>d</h4><h5>e</h5><h6>f</h6><ul><li>g</li></ul><ol><li>h</li></ol><div>i</div><section>j</section><article>k</article><blockquote>l</blockquote><table><tr><td>m</td></tr></table><figure>n</figure><pre>o</pre>trailing text'];
        yield 'an integer' => [42];
        yield 'a negative integer' => [-7];
        yield 'a float' => [1.5];
        yield 'a whole float' => [2.0];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'null' => [null];
        yield 'an empty array' => [[]];
        yield 'a list' => [['a', 'b']];
        yield 'a map' => [['a' => 1, 'b' => true, 'c' => null]];
        yield 'a list with gaps' => [[1 => 'a', 3 => 'b']];
        yield 'a nested list' => [[['a'], ['b', 'c']]];
        yield 'a pure enum' => [ExportedSuit::Hearts];
        yield 'a backed enum' => [ExportedSize::Large];
        yield 'a list of enums' => [[ExportedSize::Large, ExportedSuit::Hearts]];
    }

    #[DataProvider('values')]
    public function test_every_export_evaluates_to_the_identical_value(mixed $value): void
    {
        $code = (new PhpValueExporter())->export($value);

        self::assertNotNull($code);
        self::assertSame($value, eval('return ' . $code . ';'));
    }

    #[DataProvider('values')]
    public function test_every_export_keeps_its_value_when_the_writer_indents_its_lines(mixed $value): void
    {
        $code = (new PhpValueExporter())->export($value);
        $indented = preg_replace('/\n(?=.)/', "\n            ", (string) $code);

        self::assertSame($value, eval('return ' . $indented . ';'), $indented);
    }

    #[DataProvider('values')]
    public function test_every_line_after_the_first_is_indented_or_empty(mixed $value): void
    {
        $code = (new PhpValueExporter())->export($value);
        self::assertNotNull($code);
        $lines = explode("\n", $code);
        array_shift($lines);

        foreach ($lines as $line) {
            self::assertTrue('' === $line || str_starts_with($line, '    '), var_export($line, true));
        }
    }

    /**
     * @return iterable<string, array{\DateTimeInterface, string}>
     */
    public static function dates(): iterable
    {
        yield 'an immutable date' => [new \DateTimeImmutable('2099-01-15T09:00:00+00:00'), "new \\DateTimeImmutable('2099-01-15T09:00:00+00:00')"];
        yield 'a mutable date' => [new \DateTime('2099-01-15T09:00:00+00:00'), "new \\DateTime('2099-01-15T09:00:00+00:00')"];
        yield 'microseconds' => [new \DateTimeImmutable('2099-01-15T09:00:00.123456+00:00'), "new \\DateTimeImmutable('2099-01-15T09:00:00.123456+00:00')"];
        yield 'a mutable date with microseconds' => [new \DateTime('2099-01-15T09:00:00.000001+00:00'), "new \\DateTime('2099-01-15T09:00:00.000001+00:00')"];
        yield 'an offset' => [new \DateTimeImmutable('2099-01-15T09:00:00+02:00'), "new \\DateTimeImmutable('2099-01-15T09:00:00+02:00')"];
    }

    #[DataProvider('dates')]
    public function test_a_date_is_written_in_its_own_class_with_microseconds_only_when_present(\DateTimeInterface $date, string $expected): void
    {
        $code = (new PhpValueExporter())->export($date);

        self::assertSame($expected, $code);
        $evaluated = eval('return ' . $code . ';');
        self::assertInstanceOf($date::class, $evaluated);
        self::assertEquals($date, $evaluated);
    }

    public function test_a_list_is_written_without_keys(): void
    {
        self::assertSame("['a', 'b']", (new PhpValueExporter())->export(['a', 'b']));
    }

    public function test_a_map_keeps_its_keys(): void
    {
        self::assertSame("['a' => 1, 'b' => 'x']", (new PhpValueExporter())->export(['a' => 1, 'b' => 'x']));
    }

    public function test_a_nested_list_is_written_without_keys(): void
    {
        self::assertSame("[['a'], ['b', 'c']]", (new PhpValueExporter())->export([['a'], ['b', 'c']]));
    }

    public function test_scalars_are_written_in_lower_case(): void
    {
        $exporter = new PhpValueExporter();

        self::assertSame('null', $exporter->export(null));
        self::assertSame('true', $exporter->export(true));
        self::assertSame('false', $exporter->export(false));
        self::assertSame('[]', $exporter->export([]));
    }

    public function test_a_short_string_is_written_on_one_line(): void
    {
        self::assertSame("'it\\'s'", (new PhpValueExporter())->export("it's"));
    }

    public function test_short_html_is_not_split(): void
    {
        self::assertSame("'<p>A small team of developers and designers.</p>'", (new PhpValueExporter())->export('<p>A small team of developers and designers.</p>'));
    }

    public function test_long_html_is_split_after_each_top_level_block_closing_tag_one_piece_per_line(): void
    {
        $value = '<h2>Build pages from components</h2>'
            . '<p>CWA gives every page a layout, groups and positions, so editors can rearrange content without a deploy.</p>'
            . '<ul><li>Layouts and pages</li><li>Reusable components</li></ul>'
            . '<div><p>Nested</p><p>blocks</p></div>'
            . 'trailing text';

        self::assertSame(
            "'<h2>Build pages from components</h2>'\n"
            . "    . '<p>CWA gives every page a layout, groups and positions, so editors can rearrange content without a deploy.</p>'\n"
            . "    . '<ul><li>Layouts and pages</li><li>Reusable components</li></ul>'\n"
            . "    . '<div><p>Nested</p><p>blocks</p></div>'\n"
            . "    . 'trailing text'",
            (new PhpValueExporter())->export($value),
        );
    }

    public function test_upper_case_closing_tags_are_split(): void
    {
        $value = '<P>We started CWA to stop rebuilding the same CMS for every client.</P><P>It is open source and maintained by Silverback.</P>';

        self::assertSame(
            "'<P>We started CWA to stop rebuilding the same CMS for every client.</P>'\n"
            . "    . '<P>It is open source and maintained by Silverback.</P>'",
            (new PhpValueExporter())->export($value),
        );
    }

    public function test_a_long_string_with_no_block_closing_tag_is_written_on_one_line(): void
    {
        $value = str_repeat('plain text without any markup at all ', 5) . '<span>inline</span>';

        self::assertSame(var_export($value, true), (new PhpValueExporter())->export($value));
    }

    public function test_a_single_long_paragraph_is_never_broken(): void
    {
        $value = '<p>' . str_repeat('a', 5000) . '</p>';

        self::assertSame(var_export($value, true), (new PhpValueExporter())->export($value));
    }

    public function test_long_html_with_newlines_is_a_nowdoc_indented_to_its_closing_marker(): void
    {
        self::assertSame(
            "<<<'HTML'\n"
            . "    <h3>Getting started</h3><p>Install the bundle, then generate a scaffold from your site:</p><pre><code>composer require silverback/api-components-bundle\n"
            . "    php bin/console silverback:api-components:generate-fixtures --output=src/DataFixtures/SiteScaffold.php</code></pre>\n"
            . '    HTML',
            (new PhpValueExporter())->export(self::GETTING_STARTED),
        );
    }

    public function test_a_trailing_newline_is_an_empty_last_line_of_the_nowdoc(): void
    {
        $code = (new PhpValueExporter())->export(self::GETTING_STARTED . "\n");

        self::assertStringEndsWith("</code></pre>\n\n    HTML", (string) $code);
    }

    public function test_a_line_equal_to_the_marker_moves_the_nowdoc_to_the_next_free_marker(): void
    {
        $value = str_repeat('<p>Some paragraph text.</p>', 4) . "\nHTML\nHTML2\n<p>after</p>";
        $code = (string) (new PhpValueExporter())->export($value);

        self::assertStringStartsWith("<<<'HTML3'\n", $code);
        self::assertStringEndsWith("\n    HTML3", $code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function valuesThatCannotBeANowdoc(): iterable
    {
        yield 'CRLF line endings' => [str_repeat("<p>Line ending in CRLF.</p>\r\n", 6)];
        yield 'a line starting with a tab' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n\tindented\n<p>after</p>"];
        yield 'a control character' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n\x07<p>after</p>"];
        yield 'a line ending in spaces' => ["<pre>\n    indented\n\n\n</pre>" . str_repeat(' ', 100) . "\n"];
        yield 'a line ending in a tab' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\t\n<p>after</p>"];
        yield 'a last line ending in a space' => [str_repeat('<p>Some paragraph text.</p>', 4) . "\n<p>after</p> "];
    }

    #[DataProvider('valuesThatCannotBeANowdoc')]
    public function test_a_value_a_nowdoc_cannot_hold_falls_back_to_quoted_pieces(string $value): void
    {
        $code = (string) (new PhpValueExporter())->export($value);

        self::assertStringNotContainsString('<<<', $code);
        self::assertSame($value, eval('return ' . $code . ';'));
    }

    public function test_an_enum_is_written_fully_qualified_without_an_import_collector(): void
    {
        self::assertSame('\\' . ExportedSize::class . '::Large', (new PhpValueExporter())->export(ExportedSize::Large));
    }

    public function test_an_enum_is_imported_through_the_import_collector(): void
    {
        $imports = new ImportCollector('SiteScaffold');

        self::assertSame('ExportedSuit::Hearts', (new PhpValueExporter())->export(ExportedSuit::Hearts, $imports));
        self::assertContains('use ' . ExportedSuit::class . ';', $imports->lines());
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function unsupportedValues(): iterable
    {
        yield 'an object' => [static fn (): object => new \stdClass()];
        yield 'a closure' => [static fn (): \Closure => static fn () => null];
        yield 'a resource' => [static fn () => fopen('php://memory', 'r')];
        yield 'an object in a list' => [static fn (): array => ['a', new \stdClass()]];
        yield 'an object in a map' => [static fn (): array => ['a' => ['b' => new \stdClass()]]];
    }

    #[DataProvider('unsupportedValues')]
    public function test_a_value_that_cannot_be_written_as_php_gives_null(\Closure $value): void
    {
        self::assertNull((new PhpValueExporter())->export($value()));
    }
}
