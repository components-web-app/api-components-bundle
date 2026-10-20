<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class MaintenanceMode
{
    public const string SITE_CONFIG_KEY = 'maintenanceModeEnabled';

    private const array OFF_VALUES = ['', '0', 'false'];

    public static function storedValue(bool $on): string
    {
        return $on ? 'true' : 'false';
    }

    public static function isOn(array|string|null $value): bool
    {
        if (null === $value) {
            return false;
        }

        return \is_array($value) || !\in_array($value, self::OFF_VALUES, true);
    }
}
