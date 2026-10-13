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

final class PageUiComponent
{
    public const string PREFIX = 'CwaPage';

    public static function fullName(?string $uiComponent): ?string
    {
        if (null === $uiComponent || '' === $uiComponent || str_starts_with($uiComponent, self::PREFIX)) {
            return $uiComponent;
        }

        return self::PREFIX . $uiComponent;
    }
}
