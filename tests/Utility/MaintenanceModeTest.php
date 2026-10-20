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
use Silverback\ApiComponentsBundle\Utility\MaintenanceMode;

final class MaintenanceModeTest extends TestCase
{
    public function test_the_key_is_the_one_the_module_reads(): void
    {
        self::assertSame('maintenanceModeEnabled', MaintenanceMode::SITE_CONFIG_KEY);
    }

    public function test_the_stored_value_is_the_json_encoded_boolean_the_admin_ui_writes(): void
    {
        self::assertSame('true', MaintenanceMode::storedValue(true));
        self::assertSame('false', MaintenanceMode::storedValue(false));
    }

    /**
     * @return iterable<string, array{array<mixed>|string|null, bool}>
     */
    public static function storedValues(): iterable
    {
        yield 'no value' => [null, false];
        yield 'an empty string, which the module replaces with its default' => ['', false];
        yield '"false"' => ['false', false];
        yield '"0"' => ['0', false];
        yield '"true"' => ['true', true];
        yield '"1"' => ['1', true];
        yield 'any other string, which is truthy to the module' => ['yes', true];
        yield 'an array, which is truthy to the module' => [[], true];
    }

    /**
     * @param array<mixed>|string|null $value
     */
    #[DataProvider('storedValues')]
    public function test_a_stored_value_is_on_exactly_when_the_module_would_show_the_maintenance_page(array|string|null $value, bool $on): void
    {
        self::assertSame($on, MaintenanceMode::isOn($value));
    }
}
