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
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;

class VariableNamerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, ?string}>
     */
    public static function identifyingFields(): iterable
    {
        yield 'label first' => [['reference' => 'R', 'heading' => 'H', 'name' => 'N', 'title' => 'T', 'label' => 'L'], 'L'];
        yield 'then title' => [['reference' => 'R', 'heading' => 'H', 'name' => 'N', 'title' => 'T'], 'T'];
        yield 'then name' => [['reference' => 'R', 'heading' => 'H', 'name' => 'N'], 'N'];
        yield 'then heading' => [['reference' => 'R', 'heading' => 'H'], 'H'];
        yield 'then reference' => [['reference' => 'R'], 'R'];
        yield 'an empty value is passed over' => [['label' => '', 'title' => 'T'], 'T'];
        yield 'a blank value is passed over' => [['label' => '   ', 'title' => 'T'], 'T'];
        yield 'a value that is not a string is passed over' => [['label' => 5, 'name' => 'N'], 'N'];
        yield 'other fields without a heading are never used' => [['html' => '<p>x</p>', 'uiComponent' => 'Hero'], null];
        yield 'then the text of the first heading in any field' => [['html' => '<p>Intro</p><h3 class="x">Getting <em>started</em></h3><h2>Later</h2>'], 'Getting started'];
        yield 'a heading is decoded' => [['html' => '<H2>Q&amp;A</H2>'], 'Q&A'];
        yield 'a tag separates the words either side of it' => [['html' => '<h1>Welcome to the<br><strong>Human Behaviour Change Project</strong></h1>'], 'Welcome to the Human Behaviour Change Project'];
        yield 'a non-breaking space separates words' => [['html' => '<h2>Our&nbsp;team</h2>'], 'Our team'];
        yield 'runs of whitespace become one space' => [['html' => "<h2>\n  Our <em> </em>\tteam\n</h2>"], 'Our team'];
        yield 'an empty heading is passed over' => [['html' => '<h2> </h2>', 'body' => '<h4>Body</h4>'], 'Body'];
        yield 'a naming field wins over a heading' => [['html' => '<h2>Heading</h2>', 'label' => 'L'], 'L'];
        yield 'no fields' => [[], null];
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('identifyingFields')]
    public function test_the_identifying_value_is_the_first_non_empty_naming_field_else_the_first_html_heading(array $fields, ?string $expected): void
    {
        self::assertSame($expected, VariableNamer::identifyingValue($fields));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function variables(): iterable
    {
        yield 'words then the class' => ['DummyNavigationLink', ['Our team'], 'ourTeamDummyNavigationLink'];
        yield 'a slug' => ['DummyHtmlContent', ['about-team', 'primary'], 'aboutTeamPrimaryDummyHtmlContent'];
        yield 'capped at four words' => ['DummyComponent', ['Conference tickets on sale today'], 'conferenceTicketsOnSaleDummyComponent'];
        yield 'capped at four words across phrases' => ['DummyComponent', ['one two three', 'four five'], 'oneTwoThreeFourDummyComponent'];
        yield 'punctuation splits words' => ['DummyPublishableComponent', ['Welcome to CWA 2.0'], 'welcomeToCwa2DummyPublishableComponent'];
        yield 'upper case is lowered' => ['DummyComponent', ['CWA CONFERENCE'], 'cwaConferenceDummyComponent'];
        yield 'transliterated' => ['DummyComponent', ['Café déjà vu'], 'cafeDejaVuDummyComponent'];
        yield 'a leading number follows the first word' => ['DummyComponent', ['2024 Summit'], 'summit2024DummyComponent'];
        yield 'leading numbers follow the first word in order' => ['DummyNavigationLink', ['2024 10 Results'], 'results202410DummyNavigationLink'];
        yield 'numbers alone put the class first' => ['DummyComponent', ['2024'], 'dummyComponent2024'];
        yield 'empty words' => ['DummyComponent', ['', '!!!', '  '], 'dummyComponent'];
        yield 'a word repeated across phrases is used once' => ['DummyComponent', ['training', 'main', 'main', 'main'], 'trainingMainDummyComponent'];
        yield 'a repeated word is collapsed before the four word cap' => ['DummyImage', ['about-funding', 'main', 'main', 'gallery'], 'aboutFundingMainGalleryDummyImage'];
        yield 'a word repeated within a phrase is used once' => ['DummyComponent', ['Main main section'], 'mainSectionDummyComponent'];
        yield 'a word repeated apart is kept' => ['DummyComponent', ['main', 'side', 'main'], 'mainSideMainDummyComponent'];
        yield 'no words' => ['DummyComponent', [], 'dummyComponent'];
    }

    /**
     * @param list<string> $phrases
     */
    #[DataProvider('variables')]
    public function test_a_variable_is_the_camel_cased_words_followed_by_the_short_class_name(string $shortClassName, array $phrases, string $expected): void
    {
        self::assertSame($expected, (new VariableNamer())->variable($shortClassName, ...$phrases));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function overLongVariables(): iterable
    {
        yield 'the last words go first' => ['DummyPublishableComponent', ['Conference tickets on sale'], 'conferenceDummyPublishableComponent'];
        yield 'only as many words as needed go' => ['DummyUploadableAndPublishable', ['Home hero banner'], 'homeHeroDummyUploadableAndPublishable'];
        yield 'forty characters is kept' => ['DummyPublishableComponent', ['Welcome to London'], 'welcomeToLondonDummyPublishableComponent'];
        yield 'every word goes before the class name is touched' => ['DummyComponentWithAVeryLongName', ['Conference tickets'], 'dummyComponentWithAVeryLongName'];
    }

    /**
     * @param list<string> $phrases
     */
    #[DataProvider('overLongVariables')]
    public function test_an_identifier_over_forty_characters_loses_identifying_words_from_the_end(string $shortClassName, array $phrases, string $expected): void
    {
        $name = (new VariableNamer())->variable($shortClassName, ...$phrases);

        self::assertSame($expected, $name);
        self::assertLessThanOrEqual(40, \strlen($name));
    }

    public function test_a_class_name_alone_over_forty_characters_loses_its_leading_words_and_keeps_its_last(): void
    {
        $name = (new VariableNamer())->variable('DummyComponentWithAnExtremelyLongClassNameForTesting', 'Home');

        self::assertSame('withAnExtremelyLongClassNameForTesting', $name);
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function acronyms(): iterable
    {
        yield 'a leading acronym is lower-cased whole' => ['HTMLBlock', [], 'htmlBlock'];
        yield 'an acronym after words keeps its case' => ['HTMLBlock', ['Intro'], 'introHTMLBlock'];
        yield 'a leading digit puts the lower-cased acronym first' => ['HTMLBlock', ['2024'], 'htmlBlock2024'];
    }

    /**
     * @param list<string> $phrases
     */
    #[DataProvider('acronyms')]
    public function test_a_class_name_starting_with_an_acronym_starts_in_lower_case(string $shortClassName, array $phrases, string $expected): void
    {
        self::assertSame($expected, (new VariableNamer())->variable($shortClassName, ...$phrases));
    }

    public function test_a_clash_suffix_is_kept_within_forty_characters_by_dropping_a_word(): void
    {
        $namer = new VariableNamer();

        self::assertSame('welcomeToLondonDummyPublishableComponent', $namer->variable('DummyPublishableComponent', 'Welcome to London'));
        self::assertSame('welcomeLondonDummyPublishableComponent2', $namer->variable('DummyPublishableComponent', 'Welcome to London'));
    }

    public function test_a_method_name_is_capped_at_forty_characters(): void
    {
        $name = (new VariableNamer())->method('PageData', 'Annual international components conference');

        self::assertSame('annualInternationalComponentsPageData', $name);
        self::assertLessThanOrEqual(40, \strlen($name));
    }

    public function test_a_clash_gets_a_numeric_suffix_from_two(): void
    {
        $namer = new VariableNamer();

        self::assertSame('homePrimaryDummyHtmlContent', $namer->variable('DummyHtmlContent', 'home', 'primary'));
        self::assertSame('homePrimaryDummyHtmlContent2', $namer->variable('DummyHtmlContent', 'home', 'primary'));
        self::assertSame('homePrimaryDummyHtmlContent3', $namer->variable('DummyHtmlContent', 'home', 'primary'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function reservedNames(): iterable
    {
        yield 'this' => ['This', 'this2'];
        yield 'cwa' => ['Cwa', 'cwa2'];
        yield 'page' => ['Page', 'page2'];
        yield 'layout' => ['Layout', 'layout2'];
        yield 'pageData' => ['PageData', 'pageData2'];
    }

    #[DataProvider('reservedNames')]
    public function test_a_reserved_name_is_never_given(string $shortClassName, string $expected): void
    {
        self::assertSame($expected, (new VariableNamer())->variable($shortClassName));
    }

    public function test_a_draft_is_its_published_component_name_followed_by_draft(): void
    {
        [$published, $draft] = (new VariableNamer())->variableWithDraft('DummyPublishableComponent', 'Live');

        self::assertSame('liveDummyPublishableComponent', $published);
        self::assertSame('liveDummyPublishableComponentDraft', $draft);
    }

    public function test_a_published_component_with_a_draft_leaves_room_for_draft_so_both_share_one_stem(): void
    {
        [$published, $draft] = (new VariableNamer())->variableWithDraft('DummyPublishableComponent', 'Welcome to CWA');

        self::assertSame('welcomeCwaDummyPublishableComponent', $published);
        self::assertSame('welcomeCwaDummyPublishableComponentDraft', $draft);
        self::assertLessThanOrEqual(40, \strlen($draft));
    }

    public function test_a_long_class_name_keeps_its_last_words_and_draft(): void
    {
        [$published, $draft] = (new VariableNamer())->variableWithDraft('DummyUploadableAndPublishableComponentWidget', 'Hero');

        self::assertSame('uploadableAndPublishableComponentWidget', (new VariableNamer())->variable('DummyUploadableAndPublishableComponentWidget', 'Hero'));
        self::assertSame('andPublishableComponentWidget', $published);
        self::assertSame('andPublishableComponentWidgetDraft', $draft);
    }

    public function test_a_taken_draft_name_is_a_clash_on_the_pair_so_both_share_the_suffix(): void
    {
        $namer = new VariableNamer();
        self::assertSame('liveDummyComponentDraft', $namer->variable('DummyComponentDraft', 'Live'));

        self::assertSame(['liveDummyComponent2', 'liveDummyComponent2Draft'], $namer->variableWithDraft('DummyComponent', 'Live'));
    }

    public function test_a_draft_pair_with_a_clash_suffix_stays_within_forty_characters(): void
    {
        $namer = new VariableNamer();
        $namer->variable('VeryLongPublishableComponentDraft', 'Welcome to the conference');

        [$published, $draft] = $namer->variableWithDraft('VeryLongPublishableComponent', 'Welcome to the conference');

        self::assertSame('veryLongPublishableComponent2', $published);
        self::assertSame('veryLongPublishableComponent2Draft', $draft);
        self::assertLessThanOrEqual(40, \strlen($draft));
    }

    public function test_a_clash_suffix_after_a_number_is_separated_by_an_underscore(): void
    {
        $namer = new VariableNamer();

        self::assertSame('dummyNavigationLink2024', $namer->variable('DummyNavigationLink', '2024'));
        self::assertSame('dummyNavigationLink2024_2', $namer->variable('DummyNavigationLink', '2024'));
        self::assertSame('page404_2', (static function (VariableNamer $methods): string {
            $methods->method('Page', '404');

            return $methods->method('Page', '404');
        })(new VariableNamer()));
    }

    public function test_stop_words_are_dropped_before_meaningful_words_when_trimming(): void
    {
        self::assertSame('extremelyDummyUploadableAndPublishable', (new VariableNamer())->variable('DummyUploadableAndPublishable', 'Our extremely long hero banner title'));
        self::assertSame('ourTeamDummyNavigationLink', (new VariableNamer())->variable('DummyNavigationLink', 'Our team'));
    }

    public function test_a_name_of_stop_words_only_loses_them_from_the_end(): void
    {
        self::assertSame('ofTheDummyUploadableAndPublishableItem', (new VariableNamer())->variable('DummyUploadableAndPublishableItem', 'of the and to'));
    }

    public function test_variables_and_properties_share_one_namespace(): void
    {
        $namer = new VariableNamer();

        self::assertSame('contactUsDummyNavigationLink', $namer->variable('DummyNavigationLink', 'Contact us'));
        self::assertSame('contactUsDummyNavigationLink2', $namer->variable('DummyNavigationLink', 'Contact us'));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function methods(): iterable
    {
        yield 'a layout' => ['Layout', ['main'], 'mainLayout'];
        yield 'a page' => ['Page', ['about-team'], 'aboutTeamPage'];
        yield 'page data' => ['PageData', ['CWA Conference'], 'cwaConferencePageData'];
        yield 'redirects' => ['Redirects', [], 'redirects'];
        yield 'a leading digit puts the kind first' => ['Page', ['404'], 'page404'];
        yield 'transliterated' => ['Page', ['Über uns'], 'ueberUnsPage'];
    }

    /**
     * @param list<string> $phrases
     */
    #[DataProvider('methods')]
    public function test_a_method_is_the_camel_cased_words_followed_by_its_kind(string $kind, array $phrases, string $expected): void
    {
        self::assertSame($expected, (new VariableNamer())->method($kind, ...$phrases));
    }

    public function test_methods_have_their_own_namespace_with_clash_suffixes(): void
    {
        $namer = new VariableNamer();

        self::assertSame('homePage', $namer->method('Page', 'home'));
        self::assertSame('homePage2', $namer->method('Page', 'home'));
        self::assertSame('homePage', $namer->variable('Page', 'home'));
    }

    public function test_build_is_never_a_generated_method_name(): void
    {
        self::assertSame('build2', (new VariableNamer())->method('Build'));
    }
}
