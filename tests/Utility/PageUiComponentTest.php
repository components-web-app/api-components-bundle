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
use Silverback\ApiComponentsBundle\Utility\PageUiComponent;

class PageUiComponentTest extends TestCase
{
    public static function names(): iterable
    {
        yield 'a short name gains the prefix' => ['PrimaryPageTemplate', 'CwaPagePrimaryPageTemplate'];
        yield 'a full name is left alone' => ['CwaPagePrimaryPageTemplate', 'CwaPagePrimaryPageTemplate'];
        yield 'the prefix alone is left alone' => ['CwaPage', 'CwaPage'];
        yield 'the prefix is matched case-sensitively' => ['cwaPagePrimary', 'CwaPagecwaPagePrimary'];
        yield 'the prefix is only matched at the start' => ['MyCwaPage', 'CwaPageMyCwaPage'];
        yield 'an empty string is left for validation' => ['', ''];
        yield 'null is left for validation' => [null, null];
    }

    #[DataProvider('names')]
    public function test_the_stored_form_is_the_full_component_name(?string $value, ?string $expected): void
    {
        self::assertSame($expected, PageUiComponent::fullName($value));
    }

    public function test_the_prefix_is_the_one_the_module_registers_page_templates_with(): void
    {
        self::assertSame('CwaPage', PageUiComponent::PREFIX);
    }
}
