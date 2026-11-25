<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Utility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Utility\AllowedComponentUi;

final class AllowedComponentUiTest extends TestCase
{
    private const string IRI = '/component/images';

    /**
     * @param array<mixed>|null $allowedComponentUi
     */
    #[DataProvider('uiComponents')]
    public function test_a_ui_component_is_allowed_only_when_listed_or_unrestricted(?array $allowedComponentUi, ?string $uiComponent, bool $allowed): void
    {
        self::assertSame($allowed, AllowedComponentUi::allowsUiComponent($allowedComponentUi, self::IRI, $uiComponent));
    }

    /**
     * @return iterable<string, array{array<mixed>|null, string|null, bool}>
     */
    public static function uiComponents(): iterable
    {
        yield 'no restrictions at all' => [null, 'CwaHero', true];
        yield 'another component type restricted' => [['/component/other' => ['ui' => ['CwaOther']]], 'CwaHero', true];
        yield 'only styles restricted' => [[self::IRI => ['styles' => ['style:large']]], 'CwaHero', true];
        yield 'a listed ui' => [[self::IRI => ['ui' => ['CwaBanner', 'CwaHero']]], 'CwaHero', true];
        yield 'an unlisted ui' => [[self::IRI => ['ui' => ['CwaBanner']]], 'CwaHero', false];
        yield 'a ui differing only in case' => [[self::IRI => ['ui' => ['cwahero']]], 'CwaHero', false];
        yield 'the primary ui when null is listed' => [[self::IRI => ['ui' => [null, 'CwaHero']]], null, true];
        yield 'the primary ui when null is not listed' => [[self::IRI => ['ui' => ['CwaHero']]], null, false];
        yield 'an empty string when null is listed' => [[self::IRI => ['ui' => [null]]], '', false];
    }

    /**
     * @param array<mixed>|null $allowedComponentUi
     * @param array<mixed>|null $uiClassNames
     * @param list<string>      $disallowed
     */
    #[DataProvider('styles')]
    public function test_the_disallowed_styles_are_those_the_rule_does_not_list(?array $allowedComponentUi, ?array $uiClassNames, array $disallowed): void
    {
        self::assertSame($disallowed, AllowedComponentUi::disallowedStyles($allowedComponentUi, self::IRI, $uiClassNames));
    }

    /**
     * @return iterable<string, array{array<mixed>|null, array<mixed>|null, list<string>}>
     */
    public static function styles(): iterable
    {
        yield 'no restrictions at all' => [null, ['style:dark'], []];
        yield 'another component type restricted' => [['/component/other' => ['styles' => ['style:large']]], ['style:dark'], []];
        yield 'only ui restricted' => [[self::IRI => ['ui' => ['CwaHero']]], ['style:dark'], []];
        yield 'no styles' => [[self::IRI => ['styles' => ['style:large']]], null, []];
        yield 'an empty list of styles' => [[self::IRI => ['styles' => ['style:large']]], [], []];
        yield 'only listed styles' => [[self::IRI => ['styles' => ['style:large', 'style:dark']]], ['style:dark', 'style:large'], []];
        yield 'unlisted styles in order' => [[self::IRI => ['styles' => ['style:large']]], ['style:wide', 'style:large', 'style:dark'], ['style:wide', 'style:dark']];
        yield 'a style that is not a string' => [[self::IRI => ['styles' => ['style:large']]], [['nested' => 1], 2], ['{"nested":1}', '2']];
    }

    public function test_the_primary_ui_is_described_as_null(): void
    {
        self::assertSame('null', AllowedComponentUi::describeUiComponent(null));
        self::assertSame('CwaHero', AllowedComponentUi::describeUiComponent('CwaHero'));
    }
}
