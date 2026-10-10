<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;

class PhpMemoryLimitTest extends TestCase
{
    private string $originalLimit;

    protected function setUp(): void
    {
        $this->originalLimit = (string) \ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalLimit);
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function limits(): iterable
    {
        yield 'unlimited' => ['-1', null];
        yield 'bytes' => ['134217728', 134217728];
        yield 'kilobytes' => ['2048K', 2097152];
        yield 'megabytes' => ['512M', 536870912];
        yield 'lower case' => ['512m', 536870912];
        yield 'gigabytes' => ['1G', 1073741824];
        yield 'surrounding space' => [' 256M ', 268435456];
        yield 'empty' => ['', null];
        yield 'not a size' => ['lots', null];
        yield 'zero' => ['0', null];
    }

    #[DataProvider('limits')]
    public function test_parses_a_limit_in_php_shorthand(string $value, ?int $expected): void
    {
        self::assertSame($expected, PhpMemoryLimit::parse($value));
    }

    public function test_reads_the_current_limit(): void
    {
        ini_set('memory_limit', '768M');

        self::assertSame(805306368, (new PhpMemoryLimit())->limit());
    }

    public function test_an_unlimited_limit_reads_as_null(): void
    {
        ini_set('memory_limit', '-1');

        self::assertNull((new PhpMemoryLimit())->limit());
    }

    public function test_usage_is_the_current_allocation(): void
    {
        $usage = (new PhpMemoryLimit())->usage();

        self::assertGreaterThan(0, $usage);
        self::assertEqualsWithDelta(memory_get_usage(true), $usage, 4194304);
    }

    public function test_raising_sets_the_new_limit_and_returns_the_previous_one(): void
    {
        ini_set('memory_limit', '256M');

        $previous = (new PhpMemoryLimit())->raiseTo(536870912);

        self::assertSame('256M', $previous);
        self::assertSame('536870912', \ini_get('memory_limit'));
    }

    public function test_a_limit_already_at_or_above_the_target_is_left_alone(): void
    {
        ini_set('memory_limit', '1G');

        self::assertNull((new PhpMemoryLimit())->raiseTo(536870912));
        self::assertSame('1G', \ini_get('memory_limit'));
    }

    public function test_an_unlimited_limit_is_never_raised(): void
    {
        ini_set('memory_limit', '-1');

        self::assertNull((new PhpMemoryLimit())->raiseTo(536870912));
        self::assertSame('-1', \ini_get('memory_limit'));
    }

    public function test_restoring_puts_the_previous_limit_back(): void
    {
        ini_set('memory_limit', '512M');

        self::assertTrue((new PhpMemoryLimit())->restore('256M'));
        self::assertSame('256M', \ini_get('memory_limit'));
    }

    public function test_restoring_below_the_current_usage_fails_without_a_warning(): void
    {
        ini_set('memory_limit', '512M');
        $tooLow = (string) (int) (memory_get_usage(true) / 2);

        self::assertFalse((new PhpMemoryLimit())->restore($tooLow));
        self::assertSame('512M', \ini_get('memory_limit'));
    }
}
