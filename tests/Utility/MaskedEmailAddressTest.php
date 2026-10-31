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
use Silverback\ApiComponentsBundle\Utility\MaskedEmailAddress;

class MaskedEmailAddressTest extends TestCase
{
    #[DataProvider('addresses')]
    public function test_an_address_keeps_only_its_first_character_and_its_domain(string $emailAddress, string $masked): void
    {
        self::assertSame($masked, MaskedEmailAddress::of($emailAddress));
    }

    public static function addresses(): iterable
    {
        yield 'an address' => ['new@example.com', 'n***@example.com'];
        yield 'a one character local part' => ['n@example.com', 'n***@example.com'];
        yield 'a quoted local part holding an at sign' => ['"a@b"@example.com', '"***@example.com'];
        yield 'a multibyte first character' => ['élan@example.com', 'é***@example.com'];
        yield 'no at sign' => ['nobody', 'n***'];
        yield 'a multibyte value with no at sign' => ['éa', 'é***'];
    }
}
