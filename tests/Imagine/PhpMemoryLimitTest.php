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
        $megabytes = intdiv(memory_get_usage(true), 1048576) + 64;
        self::assertNotFalse(ini_set('memory_limit', $megabytes.'M'));

        self::assertSame($megabytes * 1048576, (new PhpMemoryLimit())->limit());
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
        $start = self::megabytesAboveUsage(64);
        $target = (string) (self::parsedLimit($start) + 67108864);
        self::assertNotFalse(ini_set('memory_limit', $start));

        $previous = (new PhpMemoryLimit())->raiseTo((int) $target);

        self::assertSame($start, $previous);
        self::assertSame($target, \ini_get('memory_limit'));
    }

    public function test_a_limit_already_at_or_above_the_target_is_left_alone(): void
    {
        $start = self::megabytesAboveUsage(128);
        self::assertNotFalse(ini_set('memory_limit', $start));

        self::assertNull((new PhpMemoryLimit())->raiseTo(self::parsedLimit($start)));
        self::assertNull((new PhpMemoryLimit())->raiseTo(self::parsedLimit($start) - 67108864));
        self::assertSame($start, \ini_get('memory_limit'));
    }

    public function test_an_unlimited_limit_is_never_raised(): void
    {
        ini_set('memory_limit', '-1');

        self::assertNull((new PhpMemoryLimit())->raiseTo(536870912));
        self::assertSame('-1', \ini_get('memory_limit'));
    }

    public function test_restoring_puts_the_previous_limit_back(): void
    {
        $previous = self::megabytesAboveUsage(64);
        self::assertNotFalse(ini_set('memory_limit', self::megabytesAboveUsage(128)));

        self::assertTrue((new PhpMemoryLimit())->restore($previous));
        self::assertSame($previous, \ini_get('memory_limit'));
    }

    public function test_restoring_below_the_current_usage_fails_without_a_warning(): void
    {
        $current = self::megabytesAboveUsage(64);
        self::assertNotFalse(ini_set('memory_limit', $current));
        $tooLow = (string) (int) (memory_get_usage(true) / 2);

        self::assertFalse((new PhpMemoryLimit())->restore($tooLow));
        self::assertSame($current, \ini_get('memory_limit'));
    }

    private static function megabytesAboveUsage(int $megabytes): string
    {
        return \sprintf('%dM', intdiv(memory_get_usage(true), 1048576) + $megabytes);
    }

    private static function parsedLimit(string $limit): int
    {
        return (int) PhpMemoryLimit::parse($limit);
    }
}
