<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Builder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Builder\IriLink;

class IriLinkTest extends TestCase
{
    public static function targets(): iterable
    {
        yield 'a page by reference' => [['page' => 'blog'], IriLink::PAGE, 'blog'];
        yield 'a layout by reference' => [['layout' => 'main'], IriLink::LAYOUT, 'main'];
        yield 'page data by its route name' => [['pageData' => 'event'], IriLink::PAGE_DATA, 'event'];
        yield 'a route by name' => [['route' => 'about'], IriLink::ROUTE, 'about'];
    }

    /**
     * @param array<string, string> $target
     */
    #[DataProvider('targets')]
    public function test_the_target_is_the_one_named(array $target, string $kind, string $name): void
    {
        $link = IriLink::to('parentPage', null, ...$target);

        self::assertSame('parentPage', $link->property);
        self::assertSame([], $link->key);
        self::assertSame($kind, $link->target);
        self::assertSame($name, $link->name);
    }

    public static function keys(): iterable
    {
        yield 'no key is the whole value' => [null, []];
        yield 'a string key' => ['parentPage', ['parentPage']];
        yield 'an integer key' => [0, [0]];
        yield 'a key path' => [['filters', 2, 'page'], ['filters', 2, 'page']];
        yield 'a key path with keys of its own is a list' => [['a' => 'filters', 'b' => 'page'], ['filters', 'page']];
    }

    /**
     * @param string|int|list<string|int>|null $key
     * @param list<string|int>                 $expected
     */
    #[DataProvider('keys')]
    public function test_a_key_is_a_path_into_the_value(string|int|array|null $key, array $expected): void
    {
        self::assertSame($expected, IriLink::to('defaultQueryParameters', $key, page: 'blog')->key);
    }

    public static function invalidTargets(): iterable
    {
        yield 'none' => [[], 0];
        yield 'two' => [['page' => 'blog', 'route' => 'blog'], 2];
        yield 'all four' => [['page' => 'a', 'layout' => 'b', 'pageData' => 'c', 'route' => 'd'], 4];
    }

    /**
     * @param array<string, string> $target
     */
    #[DataProvider('invalidTargets')]
    public function test_exactly_one_target_is_named(array $target, int $count): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('linkIri() for the property "parentPage" needs exactly one of page, layout, pageData or route; %d given.', $count));

        IriLink::to('parentPage', null, ...$target);
    }

    public function test_a_key_path_holds_only_strings_and_integers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('linkIri() for the property "defaultQueryParameters" takes a key path of strings and integers.');

        IriLink::to('defaultQueryParameters', ['filters', 1.5], page: 'blog');
    }

    public function test_the_key_path_is_written_as_it_reads(): void
    {
        self::assertSame('defaultQueryParameters', IriLink::to('defaultQueryParameters', null, page: 'blog')->describe());
        self::assertSame('defaultQueryParameters["filters"][0]', IriLink::to('defaultQueryParameters', ['filters', 0], page: 'blog')->describe());
    }
}
