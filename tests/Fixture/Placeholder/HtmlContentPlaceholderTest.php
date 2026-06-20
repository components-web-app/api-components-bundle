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

    // --- heading splice must not remove existing paragraphs ---

    public function test_heading_splice_does_not_remove_existing_paragraphs(): void
    {
        // array_splice($output, 0, 0, heading) inserts WITHOUT removing.
        // Mutations to deleteCount=-1 or 1 would eat paragraphs.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 2,
            'includeHeadings' => true,
            'includeLists' => false,
            'includeQuotes' => false,
            'includeCode' => false,
            'includeLinks' => false,
        ]);

        $this->assertSame(2, substr_count($output, '<p>'));
    }

    // --- heading position: must be first element ---

    public function test_heading_is_first_element_with_multiple_paragraphs(): void
    {
        // With paragraphs=3 the initial array_splice(output, 0, 0, heading) inserts at index 0.
        // Mutations to index -1 or 1 would move it away from first position.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 3,
            'includeHeadings' => true,
            'includeLists' => false,
            'includeQuotes' => false,
            'includeCode' => false,
            'includeLinks' => false,
        ]);

        $this->assertStringStartsWith('<h2>', $output);
    }

    // --- heading count: initial splice + (paragraphs-1) extra ---

    public function test_exactly_two_headings_with_two_paragraphs(): void
    {
        // paragraphs=2: initial splice adds 1, insertNewOutput(output, 2-1=1) always adds exactly 1 more → total 2.
        // Mutations to (paragraphs - 0) or (paragraphs + 1) could add more;
        // mutation to (paragraphs - 2 = 0) would skip extra headings → total 1.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 2,
            'includeHeadings' => true,
            'includeLists' => false,
            'includeQuotes' => false,
            'includeCode' => false,
            'includeLinks' => false,
        ]);

        $this->assertSame(2, substr_count($output, '<h2>'));
    }

    // --- links in plaintext use (url) format, not <a> ---

    public function test_links_in_plaintext_use_parenthesis_format(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeLinks' => true,
        ]);

        $this->assertStringNotContainsString('<a ', $output);
        $this->assertMatchesRegularExpression('/\(https?:\/\//', $output);
    }

    // --- list always uses <li> items ---

    public function test_list_contains_li_items(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringContainsString('<li>', $output);
    }

    // --- list plaintext uses dash prefix ---

    public function test_list_in_plaintext_uses_dash_prefix(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<li>', $output);
        $this->assertStringContainsString('- ', $output);
    }

    // --- quote plaintext uses quotation marks ---

    public function test_quote_in_plaintext_uses_quotation_marks(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeQuotes' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<blockquote>', $output);
        $this->assertStringContainsString('"', $output);
    }

    // --- code plaintext has no pre/code tags ---

    public function test_code_in_plaintext_has_no_tags(): void
    {
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeCode' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<pre>', $output);
        $this->assertStringNotContainsString('<code>', $output);
    }

    // --- Link href must contain a valid URL, not just any attribute ---

    public function test_link_href_contains_valid_url(): void
    {
        // Kills UnwrapArrayKeys mutant at line 181: without array_keys(), $phrases contains
        // URL values as elements (after shuffle re-indexing). The href would then be empty/null
        // rather than the correct URL. This test checks the href contains 'https://'.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeLinks' => true,
        ]);

        $this->assertStringContainsString('href="https://', $output);
    }

    // --- Paragraph count with zero paragraphs returns empty ---

    public function test_zero_paragraphs_returns_empty_string(): void
    {
        // Kills IncrementInteger/DecrementInteger on loop condition:
        // $i = 0; $i < $totalParagraphs means paragraphs=0 → no iterations.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 0,
            'includeHeadings' => false,
            'includeLists' => false,
            'includeQuotes' => false,
            'includeCode' => false,
            'includeLinks' => false,
        ]);

        $this->assertSame('', $output);
    }

    // --- insertNewOutput with maxInserts=0 does not add content ---

    public function test_include_lists_with_zero_paragraphs_produces_no_list(): void
    {
        // Kills GreaterThan mutation (< 1 → > 1) at line 139 in insertNewOutput.
        // With paragraphs=0, includeLists=true calls insertNewOutput($output, 0, ...).
        // With original code: 0 < 1 → return early, no list added.
        // With mutation (> 1): 0 > 1 = false → would proceed with random_int(1, 0) → ValueError.
        // But since paragraphs=0 means $output=[], insertNewOutput returns [] immediately.
        // The plain assertion is that no list element exists.
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 0,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        $this->assertStringNotContainsString('<ul>', $output);
        $this->assertStringNotContainsString('<ol>', $output);
        $this->assertStringNotContainsString('- ', $output);
    }

    // --- renderList produces valid HTML list structure ---

    public function test_list_has_opening_and_closing_tags(): void
    {
        // Kills ArrayItemRemoval on $tags = ['ul', 'ol'] (line 206):
        // removing 'ol' leaves only ['ul'], but list is still rendered with <ul></ul>.
        // This isn't fully distinguishable, but tests the basic structure.
        // More importantly this also kills FunctionCallRemoval on array_rand().
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        $this->assertMatchesRegularExpression('/<(ul|ol)>.*<\/(ul|ol)>/s', $output);
    }

    // --- renderList plain text uses dash-space prefix on first item ---

    public function test_list_in_plaintext_every_item_has_dash_prefix(): void
    {
        // Kills ConcatOperandRemoval mutant at line 214: removing the '- ' prefix
        // makes the first list item appear WITHOUT a dash. The test checks that
        // every line that is a list item starts with "- ".
        // Strategy: the plain list is "- item1\n- item2\n..." — split by "\n- " gives
        // all items. The whole string must start with "- " (the concatenated prefix).
        $output = (new HtmlContentPlaceholder())->generate([
            'paragraphs' => 1,
            'format' => HtmlContentPlaceholder::FORMAT_PLAINTEXT,
            'includeLists' => true,
            'includeLinks' => false,
        ]);

        // Extract the list block: it's the block that contains "\n- " (the implode separator)
        // The combined output may contain paragraphs and list separated by "\n\n"
        $blocks = explode("\n\n", $output);
        $listBlock = null;
        foreach ($blocks as $block) {
            if (str_contains($block, "\n- ")) {
                $listBlock = $block;
                break;
            }
        }

        $this->assertNotNull($listBlock, 'List block must be found in output');
        // The list block must start with "- " (the prefix on the first item)
        $this->assertStringStartsWith('- ', $listBlock);
    }
}
