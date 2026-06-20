<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Placeholder;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Placeholder\HtmlContentPlaceholder;

class HtmlContentPlaceholderTest extends TestCase
{
    // --- Constants ---

    public function test_format_constants_exist(): void
    {
        $this->assertSame('html', HtmlContentPlaceholder::FORMAT_HTML);
        $this->assertSame('plaintext', HtmlContentPlaceholder::FORMAT_PLAINTEXT);
    }

    public function test_length_constants_exist(): void
    {
        $this->assertSame('short', HtmlContentPlaceholder::LENGTH_SHORT);
        $this->assertSame('medium', HtmlContentPlaceholder::LENGTH_MEDIUM);
        $this->assertSame('long', HtmlContentPlaceholder::LENGTH_LONG);
    }

    // --- Default output ---

    public function test_default_generate_returns_non_empty_html_with_paragraph_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('<p>', $output);
    }

    // --- paragraphs option ---

    public function test_paragraphs_option_controls_number_of_p_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 2,
            'includeHeadings' => false,
            'includeLists' => false,
            'includeQuotes' => false,
            'includeCode' => false,
            'includeLinks' => false,
        ]);

        $this->assertSame(2, substr_count($output, '<p>'));
    }

    // --- format option ---

    public function test_plaintext_format_returns_no_html_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<', $output);
        $this->assertStringNotContainsString('>', $output);
    }

    public function test_html_format_returns_paragraph_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'format' => HtmlContentPlaceholder::FORMAT_HTML,
        ]);

        $this->assertStringContainsString('<p>', $output);
    }

    // --- includeHeadings option ---

    public function test_include_headings_true_adds_h2_element(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeHeadings' => true,
        ]);

        $this->assertStringContainsString('<h2>', $output);
    }

    public function test_include_headings_false_omits_h2_element(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeHeadings' => false,
        ]);

        $this->assertStringNotContainsString('<h2>', $output);
    }

    // --- includeLists option ---

    public function test_include_lists_true_adds_list_element(): void
    {
        // paragraphs=1 forces insertNewOutput maxInserts=1 → always inserts exactly one list
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        $this->assertMatchesRegularExpression('/<(ul|ol)>/', $output);
    }

    public function test_include_lists_false_omits_list_elements(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeLists' => false,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<ul>', $output);
        $this->assertStringNotContainsString('<ol>', $output);
    }

    // --- includeQuotes option ---

    public function test_include_quotes_true_adds_blockquote(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeQuotes' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringContainsString('<blockquote>', $output);
    }

    public function test_include_quotes_false_omits_blockquote(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeQuotes' => false,
        ]);

        $this->assertStringNotContainsString('<blockquote>', $output);
    }

    // --- includeCode option ---

    public function test_include_code_true_adds_pre_code_element(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeCode' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringContainsString('<pre><code>', $output);
    }

    public function test_include_code_false_omits_pre_code_element(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeCode' => false,
        ]);

        $this->assertStringNotContainsString('<pre><code>', $output);
    }

    // --- includeLinks option ---

    public function test_include_links_true_adds_anchor_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeLinks' => true,
        ]);

        $this->assertStringContainsString('<a href=', $output);
    }

    public function test_include_links_false_omits_anchor_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<a ', $output);
    }

    // --- paragraphLength option ---

    public function test_length_short_produces_non_empty_output(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphLength' => HtmlContentPlaceholder::LENGTH_SHORT,
            'includeLinks' => false,
        ]);

        $this->assertStringContainsString('<p>', $output);
    }

    public function test_length_long_produces_non_empty_output(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphLength' => HtmlContentPlaceholder::LENGTH_LONG,
            'includeLinks' => false,
        ]);

        $this->assertStringContainsString('<p>', $output);
    }

    // --- setOptions ---

    public function test_set_options_merges_with_defaults(): void
    {
        $placeholder = new HtmlContentPlaceholder();
        $placeholder->setOptions(['includeLinks' => false]);

        $output = $placeholder->generate();

        $this->assertStringNotContainsString('<a ', $output);
    }

    public function test_constructor_options_are_applied_as_defaults(): void
    {
        $placeholder = new HtmlContentPlaceholder(['includeLinks' => false]);

        $output = $placeholder->generate();

        $this->assertStringNotContainsString('<a ', $output);
    }

    // --- generate() call-time options override instance defaults ---

    public function test_generate_options_override_instance_defaults(): void
    {
        $placeholder = new HtmlContentPlaceholder(['includeLinks' => true]);

        $output = $placeholder->generate(['includeLinks' => false]);

        $this->assertStringNotContainsString('<a ', $output);
    }
}
