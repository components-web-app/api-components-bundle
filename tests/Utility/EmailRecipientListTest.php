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
use Silverback\ApiComponentsBundle\Utility\EmailRecipientList;

class EmailRecipientListTest extends TestCase
{
    #[DataProvider('recipientsProvider')]
    public function test_recipients_are_split_on_commas_outside_quoted_names(string $recipients, array $expected): void
    {
        self::assertSame($expected, EmailRecipientList::split($recipients));
    }

    public static function recipientsProvider(): iterable
    {
        yield 'one address' => ['a@example.com', ['a@example.com']];
        yield 'an empty string' => ['', ['']];
        yield 'bare addresses' => ['a@example.com, b@example.com', ['a@example.com', ' b@example.com']];
        yield 'a named address' => ['My Website <w@example.com>,a@example.com', ['My Website <w@example.com>', 'a@example.com']];
        yield 'a quoted name containing a comma' => ['"Smith, Jane" <j@example.com>,a@example.com', ['"Smith, Jane" <j@example.com>', 'a@example.com']];
        yield 'two quoted names containing commas' => ['"A, B" <a@example.com>,"C, D" <c@example.com>', ['"A, B" <a@example.com>', '"C, D" <c@example.com>']];
        yield 'an unquoted name containing a comma' => ['Smith, Jane <j@example.com>', ['Smith', ' Jane <j@example.com>']];
    }
}
