<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Parameter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiPlatform\Parameter\BooleanQueryValue;

class BooleanQueryValueTest extends TestCase
{
    public static function queryValues(): iterable
    {
        yield 'true' => ['true', '1'];
        yield 'TRUE' => ['TRUE', '1'];
        yield '1' => ['1', '1'];
        yield 'false' => ['false', '0'];
        yield 'False' => ['False', '0'];
        yield '0' => ['0', '0'];
        yield 'a boolean true' => [true, '1'];
        yield 'a boolean false' => [false, '0'];
        yield 'an unrecognised string is left for the filter to match nothing' => ['maybe', 'maybe'];
        yield 'a nested query value is left unchanged' => [['true'], ['true']];
        yield 'a value that is neither a string nor a boolean is left unchanged' => [1, 1];
    }

    #[DataProvider('queryValues')]
    public function test_a_query_value_is_cast_to_a_boolean_literal_doctrine_can_bind_in_a_list(mixed $value, mixed $expected): void
    {
        self::assertSame($expected, BooleanQueryValue::cast($value));
    }
}
